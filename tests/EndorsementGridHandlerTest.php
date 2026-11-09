<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use APP\core\Request;
use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\TestCase;
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
