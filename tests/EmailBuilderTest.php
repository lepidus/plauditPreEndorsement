<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use APP\submission\Submission;
use APP\publication\Publication;
use APP\author\Author;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\{
    EndorsementConfirmedEmailBuilder,
    EndorsementDeclinedEmailBuilder,
    OrcidWithoutWorksEmailBuilder
};
use APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables\{
    EndorsementConfirmed,
    EndorsementDeclined,
    EndorserOrcidWithoutWorks
};
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementPlugin;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use PKP\tests\PKPTestCase;

class EmailBuilderTest extends PKPTestCase
{
    private $submission;
    private $publication;
    private $author;
    private $endorsement;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockRequest();
        $this->initializePluginLocaleData();
        $this->createSubmission();
        $this->createEndorsement();
    }

    private function getExpectedSubject(string $templateKey): string
    {
        $contextId = $this->submission->getData('contextId');
        $template = Repo::emailTemplate()->getByKey($contextId, $templateKey);
        return $template->getLocalizedData('subject');
    }

    private function initializePluginLocaleData(): void
    {
        $plugin = new PlauditPreEndorsementPlugin();
        $plugin->pluginPath = 'plugins/generic/plauditPreEndorsement';
        $plugin->addLocaleData();
    }

    private function createSubmission()
    {
        $submission = new Submission();
        $submission->setAllData([
            'id' => 1408,
            'contextId' => 1
        ]);

        $publication = new Publication();
        $publication->setAllData([
            'id' => 2001,
            'submissionId' => $submission->getId(),
        ]);

        $author = new Author();
        $author->setAllData([
            'id' => 3001,
            'givenName' => [
                'en' => 'Alice'
            ],
            'familyName' => [
                'en' => 'Smith'
            ],
            'email' => 'alice.smith@example.com'
        ]);

        $publication->setData('authors', [$author]);
        $publication->setData('primaryContactId', $author->getId());
        $submission->setData('currentPublicationId', $publication->getId());
        $submission->setData('publications', [$publication]);

        $this->submission = $submission;
        $this->publication = $publication;
        $this->author = $author;
    }

    private function createEndorsement()
    {
        $endorsement = new Endorsement();
        $endorsement->setAllData([
            'name' => 'Caroline Johnson',
            'email' => 'caroline.johnson@example.com',
            'orcid' => '0000-0001-2345-6789'
        ]);

        $this->endorsement = $endorsement;
    }

    public function testBuildEndorsementConfirmedEmail()
    {
        $emailBuilder = new EndorsementConfirmedEmailBuilder();
        $email = $emailBuilder->setEndorsement($this->endorsement)
            ->setPublication($this->publication)
            ->buildEmailParams()
            ->build(['submission' => $this->submission]);

        $this->assertInstanceOf(EndorsementConfirmed::class, $email);

        $emailTo = $email->to;
        $this->assertCount(2, $emailTo);
        $this->assertEquals($this->endorsement->getName(), $emailTo[0]['name']);
        $this->assertEquals($this->endorsement->getEmail(), $emailTo[0]['address']);
        $this->assertEquals($this->author->getFullName(), $emailTo[1]['name']);
        $this->assertEquals($this->author->getEmail(), $emailTo[1]['address']);

        $emailParams = $email->viewData;
        $this->assertEquals($this->author->getFullName(), $emailParams['authorName']);
        $this->assertEquals($this->endorsement->getName(), $emailParams['endorserName']);
        $this->assertEquals($this->endorsement->getOrcid(), $emailParams['endorserOrcid']);

        $this->assertEquals($this->getExpectedSubject('ENDORSEMENT_CONFIRMED'), $email->subject);
    }

    public function testBuildEndorsementDeclinedEmail()
    {
        $emailBuilder = new EndorsementDeclinedEmailBuilder();
        $email = $emailBuilder->setEndorsement($this->endorsement)
            ->setPublication($this->publication)
            ->buildEmailParams()
            ->build(['submission' => $this->submission]);

        $this->assertInstanceOf(EndorsementDeclined::class, $email);

        $emailTo = $email->to;
        $this->assertCount(1, $emailTo);
        $this->assertEquals($this->author->getFullName(), $emailTo[0]['name']);
        $this->assertEquals($this->author->getEmail(), $emailTo[0]['address']);

        $emailParams = $email->viewData;
        $this->assertEquals($this->author->getFullName(), $emailParams['authorName']);
        $this->assertEquals($this->endorsement->getName(), $emailParams['endorserName']);

        $this->assertEquals($this->getExpectedSubject('ENDORSEMENT_DECLINED'), $email->subject);
    }

    public function testBuildOrcidWithoutWorksEmail()
    {
        $emailBuilder = new OrcidWithoutWorksEmailBuilder();
        $email = $emailBuilder->setEndorsement($this->endorsement)
            ->setPublication($this->publication)
            ->buildEmailParams()
            ->build(['submission' => $this->submission]);

        $this->assertInstanceOf(EndorserOrcidWithoutWorks::class, $email);

        $emailTo = $email->to;
        $this->assertCount(1, $emailTo);
        $this->assertEquals($this->author->getFullName(), $emailTo[0]['name']);
        $this->assertEquals($this->author->getEmail(), $emailTo[0]['address']);

        $emailParams = $email->viewData;
        $this->assertEquals($this->author->getFullName(), $emailParams['authorName']);
        $this->assertEquals($this->endorsement->getName(), $emailParams['endorserName']);

        $this->assertEquals($this->getExpectedSubject('ENDORSER_ORCID_WITHOUT_WORKS'), $email->subject);
    }
}
