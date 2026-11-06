<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

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
}
