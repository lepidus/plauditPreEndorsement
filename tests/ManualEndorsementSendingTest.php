<?php

use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

final class ManualEndorsementSendingTest extends TestCase
{
    private function createEndorsement(?int $status): Endorsement
    {
        $endorsement = new Endorsement();
        if (!is_null($status)) {
            $endorsement->setStatus($status);
        }

        return $endorsement;
    }

    private function createPublication(int $status): Publication
    {
        $publication = new Publication();
        $publication->setData('status', $status);

        return $publication;
    }

    public function testConfirmedOrFailedEndorsementOfPublishedPreprintCanBeSent(): void
    {
        $publishedPublication = $this->createPublication(Submission::STATUS_PUBLISHED);

        foreach ([Endorsement::STATUS_CONFIRMED, Endorsement::STATUS_COULDNT_COMPLETE] as $status) {
            $this->assertTrue(
                EndorsementService::canSendManually($this->createEndorsement($status), $publishedPublication),
                (string) $status
            );
        }
    }

    public function testEndorsementNotConfirmedByTheEndorserCannotBeSent(): void
    {
        $publishedPublication = $this->createPublication(Submission::STATUS_PUBLISHED);

        foreach ([null, Endorsement::STATUS_NOT_CONFIRMED, Endorsement::STATUS_DENIED, Endorsement::STATUS_COMPLETED] as $status) {
            $this->assertFalse(
                EndorsementService::canSendManually($this->createEndorsement($status), $publishedPublication),
                var_export($status, true)
            );
        }
    }

    public function testEndorsementOfUnpublishedPreprintCannotBeSent(): void
    {
        foreach ([Submission::STATUS_QUEUED, Submission::STATUS_DECLINED, Submission::STATUS_SCHEDULED] as $publicationStatus) {
            $this->assertFalse(
                EndorsementService::canSendManually(
                    $this->createEndorsement(Endorsement::STATUS_CONFIRMED),
                    $this->createPublication($publicationStatus)
                ),
                (string) $publicationStatus
            );
        }
    }
}
