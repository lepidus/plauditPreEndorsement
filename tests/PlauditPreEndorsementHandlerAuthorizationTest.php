<?php

import('classes.journal.Journal');
import('classes.submission.Submission');
import('classes.submission.SubmissionDAO');
import('classes.core.Request');
import('lib.pkp.classes.core.PKPPageRouter');
import('lib.pkp.classes.core.Dispatcher');
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');
import('plugins.generic.plauditPreEndorsement.classes.PlauditPreEndorsementHandler');

use PHPUnit\Framework\TestCase;

final class PlauditPreEndorsementHandlerAuthorizationTest extends TestCase
{
    private const CONTEXT_ID = 1;
    private const SUBMISSION_ID = 10;
    private const SUBMISSION_OPERATIONS = ['updateEndorser', 'removeEndorsement', 'sendEndorsementManually'];

    private $originalSubmissionDao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSubmissionDao = DAORegistry::getDAO('SubmissionDAO');
    }

    protected function tearDown(): void
    {
        DAORegistry::registerDAO('SubmissionDAO', $this->originalSubmissionDao);
        parent::tearDown();
    }

    private function registerSubmissionFromContext(int $contextId): void
    {
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('contextId', $contextId);

        $submissionDao = $this->getMockBuilder(SubmissionDAO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getById'])
            ->getMock();
        $submissionDao->method('getById')->willReturn($submission);
        DAORegistry::registerDAO('SubmissionDAO', $submissionDao);
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
            ->onlyMethods(['getRouter', 'getContext', 'getDispatcher', 'getUserVar', 'isPost', 'checkCSRF'])
            ->getMock();
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
