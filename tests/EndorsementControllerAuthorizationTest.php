<?php

use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\plauditPreEndorsement\classes\api\v1\EndorsementController;
use APP\submission\Submission;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;
use PKP\middleware\PolicyAuthorizer;
use PKP\security\authorization\AuthorizationDecisionManager;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

final class EndorsementControllerAuthorizationTest extends TestCase
{
    private const AUTHOR_OPERATIONS = ['getEndorsements', 'addEndorsement', 'editEndorsement', 'deleteEndorsement'];
    private const ALL_OPERATIONS = [...self::AUTHOR_OPERATIONS, 'sendEndorsement'];

    private static ?string $routePrefix = null;

    /**
     * Registers the controller routes once, under a prefix of their own.
     */
    private function getRoutePrefix(): string
    {
        if (is_null(self::$routePrefix)) {
            self::$routePrefix = 'plauditEndorsementsAuthorizationTest' . uniqid();
            $controller = new EndorsementController();
            app('router')->group(
                ['prefix' => self::$routePrefix, 'middleware' => $controller->getRouteGroupMiddleware()],
                $controller->getGroupRoutes(...)
            );
        }

        return self::$routePrefix;
    }

    /**
     * Role => operations map that the core PolicyAuthorizer computes from the
     * controller routes and passes as $roleAssignments to authorize() and,
     * from there, to the core ContextAccessPolicy and SubmissionAccessPolicy.
     */
    private function getRoleAssignments(): array
    {
        $prefix = $this->getRoutePrefix();
        $controllerRoutes = new RouteCollection();
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), $prefix . '/')) {
                $controllerRoutes->add($route);
            }
        }

        $policyAuthorizer = new class () extends PolicyAuthorizer {
            public function getRoleAssignments(RouteCollection $routes): array
            {
                return $this->getRoleAssignmentMap($routes)->toArray();
            }
        };

        return $policyAuthorizer->getRoleAssignments($controllerRoutes);
    }

    /**
     * Runs EndorsementController::authorize() for a route and returns the
     * policies it registers. The policies are evaluated by the core; here the
     * decision manager only records them.
     */
    private function getRegisteredPolicies(string $method, string $path): array
    {
        $controller = new class () extends EndorsementController {
            public function __construct()
            {
                $this->_authorizationDecisionManager = new class () extends AuthorizationDecisionManager {
                    public array $policies = [];

                    public function addPolicy($policyOrPolicySet, $addToTop = false)
                    {
                        $this->policies[] = $policyOrPolicySet;
                    }

                    public function decide()
                    {
                        return AuthorizationPolicy::AUTHORIZATION_DENY;
                    }
                };
            }

            public function getRegisteredPolicies(): array
            {
                return $this->_authorizationDecisionManager->policies;
            }
        };
        $args = [IlluminateRequest::create('/' . $this->getRoutePrefix() . $path, $method)];

        $controller->authorize($this->createMock(Request::class), $args, $this->getRoleAssignments());

        return $controller->getRegisteredPolicies();
    }

    private function getRegisteredPolicyClasses(string $method, string $path): array
    {
        return array_map(fn ($policy) => $policy::class, $this->getRegisteredPolicies($method, $path));
    }

    private function getSendingStageRolePolicy(): StageRolePolicy
    {
        $stageRolePolicies = array_values(array_filter(
            $this->getRegisteredPolicies('POST', '/1/2/send'),
            fn ($policy) => $policy instanceof StageRolePolicy
        ));
        $this->assertCount(1, $stageRolePolicies);

        return $stageRolePolicies[0];
    }

    /**
     * Decision of the sending StageRolePolicy for a user with the given roles
     * in the context and the given accessible workflow stages, as computed by
     * the core (Repo::user()->getAccessibleWorkflowStages).
     */
    private function canSend(array $userRoles, array $accessibleStageRoles): bool
    {
        $submission = new Submission();
        $submission->setId(1);
        $submission->setData('stageId', WORKFLOW_STAGE_ID_PRODUCTION);
        $authorizedContext = [
            Application::ASSOC_TYPE_SUBMISSION => $submission,
            Application::ASSOC_TYPE_USER_ROLES => $userRoles,
            Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES => empty($accessibleStageRoles)
                ? []
                : [WORKFLOW_STAGE_ID_PRODUCTION => $accessibleStageRoles],
        ];

        $policy = $this->getSendingStageRolePolicy();
        $policy->setAuthorizedContext($authorizedContext);

        return $policy->effect() === AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }

    public function testSubmissionAccessIsDecidedByCorePolicies(): void
    {
        foreach ([['GET', '/1'], ['POST', '/1'], ['PUT', '/1/2'], ['DELETE', '/1/2'], ['POST', '/1/2/send']] as [$method, $path]) {
            $registeredPolicyClasses = $this->getRegisteredPolicyClasses($method, $path);

            $this->assertContains(UserRolesRequiredPolicy::class, $registeredPolicyClasses, "$method $path");
            $this->assertContains(ContextAccessPolicy::class, $registeredPolicyClasses, "$method $path");
            $this->assertContains(SubmissionAccessPolicy::class, $registeredPolicyClasses, "$method $path");
        }
    }

    public function testOnlySendingRequiresAnEditorialStageRole(): void
    {
        foreach ([['GET', '/1'], ['POST', '/1'], ['PUT', '/1/2'], ['DELETE', '/1/2']] as [$method, $path]) {
            $this->assertNotContains(StageRolePolicy::class, $this->getRegisteredPolicyClasses($method, $path), "$method $path");
        }
        $this->assertContains(StageRolePolicy::class, $this->getRegisteredPolicyClasses('POST', '/1/2/send'));
    }

    public function testAuthorsMayManageButNotSendEndorsements(): void
    {
        $roleAssignments = $this->getRoleAssignments();

        $this->assertEqualsCanonicalizing(self::AUTHOR_OPERATIONS, $roleAssignments[Role::ROLE_ID_AUTHOR]);
        $this->assertNotContains('sendEndorsement', $roleAssignments[Role::ROLE_ID_AUTHOR]);
    }

    public function testEditorialRolesMayRunEveryOperation(): void
    {
        $roleAssignments = $this->getRoleAssignments();

        foreach ([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT] as $roleId) {
            $this->assertEqualsCanonicalizing(self::ALL_OPERATIONS, $roleAssignments[$roleId], (string) $roleId);
        }
    }

    public function testOtherRolesHaveNoOperation(): void
    {
        $roleAssignments = $this->getRoleAssignments();

        $this->assertEqualsCanonicalizing(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            array_keys($roleAssignments)
        );
    }

    public function testSubEditorAssignedOnlyAsAuthorCannotSendEndorsement(): void
    {
        $this->assertFalse($this->canSend([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR], [Role::ROLE_ID_AUTHOR]));
        $this->assertFalse($this->canSend([Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR], [Role::ROLE_ID_AUTHOR]));
    }

    public function testManagerAssignedAsAuthorCanSendEndorsement(): void
    {
        $this->assertTrue($this->canSend([Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR], [Role::ROLE_ID_AUTHOR]));
    }

    public function testEditoriallyAssignedUsersCanSendEndorsement(): void
    {
        $this->assertTrue($this->canSend([Role::ROLE_ID_SUB_EDITOR], [Role::ROLE_ID_SUB_EDITOR]));
        $this->assertTrue($this->canSend([Role::ROLE_ID_ASSISTANT], [Role::ROLE_ID_ASSISTANT]));
        $this->assertTrue($this->canSend([Role::ROLE_ID_MANAGER], [Role::ROLE_ID_MANAGER]));
        $this->assertTrue($this->canSend([Role::ROLE_ID_SITE_ADMIN], [Role::ROLE_ID_SITE_ADMIN]));
    }

    public function testUserWithoutEditorialAssignmentCannotSendEndorsement(): void
    {
        $this->assertFalse($this->canSend([Role::ROLE_ID_SUB_EDITOR], []));
        $this->assertFalse($this->canSend([Role::ROLE_ID_ASSISTANT], []));
    }
}
