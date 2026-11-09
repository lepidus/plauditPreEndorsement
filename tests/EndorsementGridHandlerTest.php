<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use APP\core\Application;
use APP\core\Request;
use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\TestCase;
use PKP\core\PKPComponentRouter;
use PKP\security\authorization\AuthorizationDecisionManager;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\controllers\grid\EndorsementGridHandler;

class EndorsementGridHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('PLAUDIT_PRE_ENDORSEMENT_PLUGIN_NAME')) {
            define('PLAUDIT_PRE_ENDORSEMENT_PLUGIN_NAME', 'plauditpreendorsementplugin');
        }
    }

    private function createSubmissionWithPublications(array $publicationIds): Submission
    {
        $publications = [];
        foreach ($publicationIds as $publicationId) {
            $publication = new Publication();
            $publication->setId($publicationId);
            $publications[] = $publication;
        }

        $submission = new Submission();
        $submission->setId(10);
        $submission->setData('publications', $publications);

        return $submission;
    }

    private function createEndorsement(int $publicationId): Endorsement
    {
        $endorsement = new Endorsement();
        $endorsement->setId(100);
        $endorsement->setPublicationId($publicationId);

        return $endorsement;
    }

    public function testEndorsementOfSubmissionPublicationIsAccepted(): void
    {
        $submission = $this->createSubmissionWithPublications([1, 2]);

        $this->assertTrue(EndorsementGridHandler::endorsementBelongsToSubmission($this->createEndorsement(1), $submission));
        $this->assertTrue(EndorsementGridHandler::endorsementBelongsToSubmission($this->createEndorsement(2), $submission));
    }

    public function testEndorsementOfAnotherSubmissionIsRejected(): void
    {
        $submission = $this->createSubmissionWithPublications([1, 2]);

        $this->assertFalse(EndorsementGridHandler::endorsementBelongsToSubmission($this->createEndorsement(3), $submission));
    }

    public function testMissingEndorsementOrPublicationsAreRejected(): void
    {
        $this->assertFalse(EndorsementGridHandler::endorsementBelongsToSubmission(null, $this->createSubmissionWithPublications([1])));
        $this->assertFalse(EndorsementGridHandler::endorsementBelongsToSubmission($this->createEndorsement(1), $this->createSubmissionWithPublications([])));
    }

    /**
     * Runs EndorsementGridHandler::authorize() for an operation and returns
     * the policies it registers. The policies are evaluated by the core; here
     * the decision manager only records them.
     */
    private function getRegisteredPolicies(string $operation): array
    {
        $router = $this->getMockBuilder(PKPComponentRouter::class)
            ->onlyMethods(['getRequestedOp'])
            ->getMock();
        $router->method('getRequestedOp')->willReturn($operation);
        $request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['getRouter', 'getUser'])
            ->getMock();
        $request->method('getRouter')->willReturn($router);
        $request->method('getUser')->willReturn(null);

        $handler = new EndorsementGridHandler();
        $handler->_authorizationDecisionManager = new class () extends AuthorizationDecisionManager {
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
        $args = ['submissionId' => '10'];

        $handler->authorize($request, $args, $handler->getRoleAssignments());

        return $handler->_authorizationDecisionManager->policies;
    }

    private function getSendingStageRolePolicy(): StageRolePolicy
    {
        $stageRolePolicies = array_values(array_filter(
            $this->getRegisteredPolicies('sendEndorsementManually'),
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
        $submission = $this->createSubmissionWithPublications([1]);
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

    public function testEveryOperationUsesSubmissionAccessPolicy(): void
    {
        foreach (['fetchGrid', 'editEndorsement', 'deleteEndorsement', 'sendEndorsementManually'] as $operation) {
            $policyClasses = array_map(fn ($policy) => $policy::class, $this->getRegisteredPolicies($operation));
            $this->assertContains(SubmissionAccessPolicy::class, $policyClasses, $operation);
        }
    }

    public function testOnlySendingRequiresAnEditorialStageRole(): void
    {
        foreach (['fetchGrid', 'fetchRow', 'addEndorsement', 'editEndorsement', 'updateEndorsement', 'deleteEndorsement'] as $operation) {
            $policyClasses = array_map(fn ($policy) => $policy::class, $this->getRegisteredPolicies($operation));
            $this->assertNotContains(StageRolePolicy::class, $policyClasses, $operation);
        }
        $this->getSendingStageRolePolicy();
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
    }

    public function testUserWithoutEditorialAssignmentCannotSendEndorsement(): void
    {
        $this->assertFalse($this->canSend([Role::ROLE_ID_SUB_EDITOR], []));
        $this->assertFalse($this->canSend([Role::ROLE_ID_ASSISTANT], []));
    }

    public function testAuthorCannotSendEndorsementManually(): void
    {
        $handler = new EndorsementGridHandler();

        $this->assertNotContains('sendEndorsementManually', $handler->getRoleAssignment(Role::ROLE_ID_AUTHOR));
        foreach ([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT] as $roleId) {
            $this->assertContains('sendEndorsementManually', $handler->getRoleAssignment($roleId));
        }
    }

    private function createRequest(bool $isPost, bool $validCsrf)
    {
        $request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['isPost', 'checkCSRF', 'getUserVar', 'getContext'])
            ->getMock();
        $request->method('isPost')->willReturn($isPost);
        $request->method('checkCSRF')->willReturn($validCsrf);
        $request->method('getUserVar')->willReturn('100');
        $request->expects($this->never())->method('getContext');

        return $request;
    }

    private function createSendingRequest()
    {
        $context = $this->getMockBuilder(\APP\server\Server::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $context->method('getId')->willReturn(1);

        $request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['isPost', 'checkCSRF', 'getUserVar', 'getContext', 'getUser'])
            ->getMock();
        $request->method('isPost')->willReturn(true);
        $request->method('checkCSRF')->willReturn(true);
        $request->method('getUserVar')->willReturn('100');
        $request->method('getContext')->willReturn($context);
        $request->expects($this->never())->method('getUser');

        return $request;
    }

    public function testSendingRefusesEndorsementNotReadyToSend(): void
    {
        $notReadyCases = [
            [Submission::STATUS_PUBLISHED, Endorsement::STATUS_NOT_CONFIRMED],
            [Submission::STATUS_PUBLISHED, Endorsement::STATUS_DENIED],
            [Submission::STATUS_PUBLISHED, Endorsement::STATUS_COMPLETED],
            [Submission::STATUS_QUEUED, Endorsement::STATUS_CONFIRMED],
        ];

        foreach ($notReadyCases as [$publicationStatus, $endorsementStatus]) {
            $submission = $this->createSubmissionWithPublications([1]);
            $submission->getData('publications')[0]->setData('status', $publicationStatus);
            $submission->setData('currentPublicationId', 1);
            $endorsement = $this->createEndorsement(1);
            $endorsement->setStatus($endorsementStatus);

            $handler = $this->getMockBuilder(EndorsementGridHandler::class)
                ->onlyMethods(['getSubmission', 'getSubmissionEndorsement'])
                ->getMock();
            $handler->method('getSubmission')->willReturn($submission);
            $handler->method('getSubmissionEndorsement')->willReturn($endorsement);

            $response = $handler->sendEndorsementManually([], $this->createSendingRequest());

            $this->assertFalse($response->getStatus(), "$publicationStatus/$endorsementStatus");
            $this->assertSame(__('plugins.generic.plauditPreEndorsement.endorsementNotReadyToSend'), $response->getContent());
        }
    }

    public function testStateChangingRowActionsRequirePostWithCsrfToken(): void
    {
        $invalidRequests = [[false, false], [false, true], [true, false]];

        foreach (['deleteEndorsement', 'sendEndorsementManually'] as $operation) {
            foreach ($invalidRequests as [$isPost, $validCsrf]) {
                $handler = $this->getMockBuilder(EndorsementGridHandler::class)
                    ->onlyMethods(['getSubmission'])
                    ->getMock();
                $handler->expects($this->never())->method('getSubmission');

                $response = $handler->$operation([], $this->createRequest($isPost, $validCsrf));

                $this->assertFalse($response->getStatus(), $operation);
            }
        }
    }
}
