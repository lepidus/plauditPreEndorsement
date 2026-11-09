<?php

use APP\core\Request;
use APP\plugins\generic\plauditPreEndorsement\classes\api\v1\EndorsementController;
use Illuminate\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;
use PKP\middleware\PolicyAuthorizer;
use PKP\security\authorization\AuthorizationDecisionManager;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

final class EndorsementControllerAuthorizationTest extends TestCase
{
    private const AUTHOR_OPERATIONS = ['getEndorsements', 'addEndorsement', 'editEndorsement', 'deleteEndorsement'];
    private const ALL_OPERATIONS = [...self::AUTHOR_OPERATIONS, 'sendEndorsement'];

    /**
     * Registers the controller routes and returns the role => operations
     * map that the core PolicyAuthorizer passes as $roleAssignments to
     * EndorsementController::authorize() and, from there, to the core
     * ContextAccessPolicy and SubmissionAccessPolicy.
     */
    private function getRoleAssignments(): array
    {
        $controller = new EndorsementController();
        $router = app('router');
        $prefix = 'plauditEndorsementsAuthorizationTest' . uniqid();
        $router->group(
            ['prefix' => $prefix, 'middleware' => $controller->getRouteGroupMiddleware()],
            $controller->getGroupRoutes(...)
        );

        $controllerRoutes = new RouteCollection();
        foreach ($router->getRoutes()->getRoutes() as $route) {
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
     * The policies themselves are evaluated by the core; here the decision
     * manager only records which policies the controller registers.
     */
    public function testSubmissionAccessIsDecidedByCorePolicies(): void
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

            public function getRegisteredPolicyClasses(): array
            {
                return array_map(fn ($policy) => $policy::class, $this->_authorizationDecisionManager->policies);
            }
        };
        $args = [];

        $controller->authorize($this->createMock(Request::class), $args, $this->getRoleAssignments());

        $registeredPolicyClasses = $controller->getRegisteredPolicyClasses();
        $this->assertContains(UserRolesRequiredPolicy::class, $registeredPolicyClasses);
        $this->assertContains(ContextAccessPolicy::class, $registeredPolicyClasses);
        $this->assertContains(SubmissionAccessPolicy::class, $registeredPolicyClasses);
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
}
