<?php

import('classes.journal.Journal');
import('classes.submission.Submission');
import('classes.submission.SubmissionDAO');
import('classes.core.Request');
import('lib.pkp.classes.core.PKPPageRouter');
import('lib.pkp.classes.core.Dispatcher');
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
import('lib.pkp.classes.core.ArrayItemIterator');
import('lib.pkp.classes.stageAssignment.StageAssignment');
import('lib.pkp.classes.stageAssignment.StageAssignmentDAO');
import('lib.pkp.classes.security.UserGroup');
import('lib.pkp.classes.security.UserGroupDAO');
import('lib.pkp.classes.user.User');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');
import('plugins.generic.plauditPreEndorsement.classes.PlauditPreEndorsementHandler');

use PHPUnit\Framework\TestCase;

final class PlauditPreEndorsementHandlerAuthorizationTest extends TestCase
{
    private const CONTEXT_ID = 1;
    private const SUBMISSION_ID = 10;
    private const USER_ID = 20;
    private const SUBMISSION_OPERATIONS = ['updateEndorser', 'removeEndorsement', 'sendEndorsementManually'];

    private $originalSubmissionDao;
    private $originalStageAssignmentDao;
    private $originalUserGroupDao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSubmissionDao = DAORegistry::getDAO('SubmissionDAO');
        $this->originalStageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $this->originalUserGroupDao = DAORegistry::getDAO('UserGroupDAO');
    }

    protected function tearDown(): void
    {
        DAORegistry::registerDAO('SubmissionDAO', $this->originalSubmissionDao);
        DAORegistry::registerDAO('StageAssignmentDAO', $this->originalStageAssignmentDao);
        DAORegistry::registerDAO('UserGroupDAO', $this->originalUserGroupDao);
        parent::tearDown();
    }

    private function registerSubmissionFromContext(int $contextId): void
    {
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('contextId', $contextId);
        $submission->setData('stageId', WORKFLOW_STAGE_ID_PRODUCTION);

        $submissionDao = $this->getMockBuilder(SubmissionDAO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getById'])
            ->getMock();
        $submissionDao->method('getById')->willReturn($submission);
        DAORegistry::registerDAO('SubmissionDAO', $submissionDao);
    }

    /**
     * @param int[] $assignedRoleIds Roles of the user groups in which the user is assigned to the submission
     */
    private function registerStageAssignments(array $assignedRoleIds): void
    {
        $stageAssignments = [];
        $userGroups = [];
        foreach ($assignedRoleIds as $index => $roleId) {
            $userGroupId = 100 + $index;
            $stageAssignment = new StageAssignment();
            $stageAssignment->setSubmissionId(self::SUBMISSION_ID);
            $stageAssignment->setUserId(self::USER_ID);
            $stageAssignment->setUserGroupId($userGroupId);
            $stageAssignment->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
            $stageAssignments[] = $stageAssignment;

            $userGroup = new UserGroup();
            $userGroup->setId($userGroupId);
            $userGroup->setRoleId($roleId);
            $userGroups[$userGroupId] = $userGroup;
        }

        $stageAssignmentDao = $this->getMockBuilder(StageAssignmentDAO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBySubmissionAndUserIdAndStageId'])
            ->getMock();
        $stageAssignmentDao->method('getBySubmissionAndUserIdAndStageId')
            ->willReturnCallback(function ($submissionId, $userId) use ($stageAssignments) {
                $isRequestedUser = (int) $submissionId === self::SUBMISSION_ID && (int) $userId === self::USER_ID;
                $userStageAssignments = $isRequestedUser ? $stageAssignments : [];
                return new ArrayItemIterator($userStageAssignments);
            });
        DAORegistry::registerDAO('StageAssignmentDAO', $stageAssignmentDao);

        $userGroupDao = $this->getMockBuilder(UserGroupDAO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getById'])
            ->getMock();
        $userGroupDao->method('getById')->willReturnCallback(function ($userGroupId) use ($userGroups) {
            return $userGroups[$userGroupId] ?? null;
        });
        DAORegistry::registerDAO('UserGroupDAO', $userGroupDao);
    }

    private function createRequest(string $operation, array $userVars = [])
    {
        $context = new Journal();
        $context->setId(self::CONTEXT_ID);

        $router = $this->getMockBuilder(PKPPageRouter::class)
            ->onlyMethods(['getRequestedOp', 'getContext'])
            ->getMock();
        $router->method('getRequestedOp')->willReturn($operation);
        $router->method('getContext')->willReturn($context);

        $dispatcher = $this->getMockBuilder(Dispatcher::class)
            ->onlyMethods(['handle404'])
            ->getMock();

        $request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['getRouter', 'getContext', 'getDispatcher', 'getUserVar', 'isPost', 'checkCSRF', 'getUser'])
            ->getMock();
        $user = new User();
        $user->setId(self::USER_ID);
        $request->method('getUser')->willReturn($user);
        $request->method('getRouter')->willReturn($router);
        $request->method('getContext')->willReturn($context);
        $request->method('getDispatcher')->willReturn($dispatcher);
        $request->method('getUserVar')->willReturnCallback(function ($name) use ($userVars) {
            return $userVars[$name] ?? null;
        });
        $request->method('isPost')->willReturn(false);
        $request->method('checkCSRF')->willReturn(false);

        $router->setApplication(Application::get());
        $router->setDispatcher($dispatcher);
        $router->setHandler(null);

        return $request;
    }

    private function authorize(string $operation, ?array $userRoles): bool
    {
        $request = $this->createRequest($operation, ['submissionId' => (string) self::SUBMISSION_ID]);
        $handler = new PlauditPreEndorsementHandler();
        $request->getRouter()->setHandler($handler);

        if (!is_null($userRoles)) {
            $handler->addPolicy(new PlauditTestUserRolesPolicy($request, $userRoles), true);
        }

        $args = [];
        return $handler->authorize($request, $args, $handler->getRoleAssignments());
    }

    public function testSubmissionOperationsAreDeniedWithoutUserRoles(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);

        foreach (self::SUBMISSION_OPERATIONS as $operation) {
            $this->assertFalse($this->authorize($operation, null), $operation);
        }
    }

    public function testManagerIsAuthorizedForSubmissionOperations(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);

        foreach (self::SUBMISSION_OPERATIONS as $operation) {
            $this->assertTrue($this->authorize($operation, [ROLE_ID_MANAGER]), $operation);
        }
    }

    public function testSubmissionFromAnotherContextIsDenied(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID + 1);

        foreach (self::SUBMISSION_OPERATIONS as $operation) {
            $this->assertFalse($this->authorize($operation, [ROLE_ID_MANAGER]), $operation);
        }
    }

    public function testAuthorCannotRemoveOrSendEndorsement(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);

        $this->assertFalse($this->authorize('removeEndorsement', [ROLE_ID_AUTHOR]));
        $this->assertFalse($this->authorize('sendEndorsementManually', [ROLE_ID_AUTHOR]));
    }

    public function testAssignedAuthorIsAuthorizedToUpdateEndorser(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);
        $this->registerStageAssignments([ROLE_ID_AUTHOR]);

        $this->assertTrue($this->authorize('updateEndorser', [ROLE_ID_AUTHOR]));
    }

    public function testUnassignedAuthorOrSubEditorIsDeniedToUpdateEndorser(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);
        $this->registerStageAssignments([]);

        $this->assertFalse($this->authorize('updateEndorser', [ROLE_ID_AUTHOR]));
        $this->assertFalse($this->authorize('updateEndorser', [ROLE_ID_SUB_EDITOR]));
        $this->assertFalse($this->authorize('updateEndorser', [ROLE_ID_ASSISTANT]));
    }

    public function testAssignedSubEditorIsAuthorizedToUpdateEndorser(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);
        $this->registerStageAssignments([ROLE_ID_SUB_EDITOR]);

        $this->assertTrue($this->authorize('updateEndorser', [ROLE_ID_SUB_EDITOR]));
    }

    public function testSubEditorOrAssistantAssignedOnlyAsAuthorCannotRemoveOrSendEndorsement(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);
        $this->registerStageAssignments([ROLE_ID_AUTHOR]);

        foreach ([ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT] as $editorialRole) {
            $this->assertTrue($this->authorize('updateEndorser', [$editorialRole, ROLE_ID_AUTHOR]), (string) $editorialRole);
            $this->assertFalse($this->authorize('removeEndorsement', [$editorialRole, ROLE_ID_AUTHOR]), (string) $editorialRole);
            $this->assertFalse($this->authorize('sendEndorsementManually', [$editorialRole, ROLE_ID_AUTHOR]), (string) $editorialRole);
        }
    }

    public function testEditoriallyAssignedSubEditorOrAssistantCanRemoveOrSendEndorsement(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);

        foreach ([ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT] as $editorialRole) {
            $this->registerStageAssignments([$editorialRole]);
            $this->assertTrue($this->authorize('removeEndorsement', [$editorialRole]), (string) $editorialRole);
            $this->assertTrue($this->authorize('sendEndorsementManually', [$editorialRole]), (string) $editorialRole);
        }
    }

    public function testManagerAssignedAsAuthorIsAuthorizedToRemoveOrSendEndorsement(): void
    {
        $this->registerSubmissionFromContext(self::CONTEXT_ID);
        $this->registerStageAssignments([ROLE_ID_AUTHOR]);

        $this->assertTrue($this->authorize('removeEndorsement', [ROLE_ID_MANAGER, ROLE_ID_AUTHOR]));
        $this->assertTrue($this->authorize('sendEndorsementManually', [ROLE_ID_MANAGER, ROLE_ID_AUTHOR]));
    }

    public function testOrcidVerifyRemainsPublic(): void
    {
        $this->assertTrue($this->authorize('orcidVerify', null));
    }

    public function testSubmissionOperationsRequirePostWithCsrfToken(): void
    {
        foreach (self::SUBMISSION_OPERATIONS as $operation) {
            $handler = $this->getMockBuilder(PlauditPreEndorsementHandler::class)
                ->onlyMethods(['getAuthorizedContextObject'])
                ->getMock();
            $handler->expects($this->never())->method('getAuthorizedContextObject');

            $handler->$operation([], $this->createRequest($operation));
        }
    }
}

class PlauditTestUserRolesPolicy extends AuthorizationPolicy
{
    private $userRoles;

    public function __construct($request, array $userRoles)
    {
        parent::__construct();
        $this->userRoles = $userRoles;
    }

    public function effect()
    {
        $this->addAuthorizedContextObject(ASSOC_TYPE_USER_ROLES, $this->userRoles);
        return AUTHORIZATION_PERMIT;
    }
}
