<?php

use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

final class ConfirmedEndorsementSendingTest extends TestCase
{
    private function createEndorsementService(): EndorsementService
    {
        return $this->getMockBuilder(EndorsementService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendEndorsement'])
            ->getMock();
    }

    private function createPublication(int $status): Publication
    {
        $publication = new Publication();
        $publication->setData('status', $status);

        return $publication;
    }

    public function testConfirmedEndorsementIsSentWhenPublicationIsPublished(): void
    {
        $endorsement = new Endorsement();
        $endorsementService = $this->createEndorsementService();
        $endorsementService->expects($this->once())
            ->method('sendEndorsement')
            ->with($this->identicalTo($endorsement));

        $endorsementService->sendConfirmedEndorsementIfPublished(
            $endorsement,
            $this->createPublication(Submission::STATUS_PUBLISHED)
        );
    }

    public function testConfirmedEndorsementIsNotSentBeforePublication(): void
    {
        $endorsementService = $this->createEndorsementService();
        $endorsementService->expects($this->never())->method('sendEndorsement');

        $endorsementService->sendConfirmedEndorsementIfPublished(
            new Endorsement(),
            $this->createPublication(Submission::STATUS_QUEUED)
        );
    }
}
