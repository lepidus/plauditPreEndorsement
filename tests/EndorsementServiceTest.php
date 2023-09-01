<?php

import('classes.publication.Publication');
import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.plauditPreEndorsement.classes.CrossrefClient');
import('plugins.generic.plauditPreEndorsement.classes.EndorsementService');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');

final class EndorsementServiceTest extends DatabaseTestCase
{
    private $endorsementService;
    private $contextId = 1;
    private $submissionId = 1234;
    private $publication;
    private $plugin;
    private $doi = '10.1234/TestePublication.1234';
    private $secretKey = 'a1b2c3d4-e5f6g7h8';

    public function setUp(): void
    {
        parent::setUp();
        $this->publication = new Publication();
        $this->plugin = new PlauditPreEndorsementPlugin();
        $this->endorsementService = new EndorsementService($this->contextId, $this->plugin);
        $this->endorsementService->setCrossrefClient(new CrossrefClient());
    }

    protected function getAffectedTables(): array
    {
        return array('event_log', 'event_log_settings', 'plugin_settings');
    }

    private function getMockCrossrefClient()
    {
        $mockCrossrefClient = $this->createMock(CrossrefClient::class);
        $mockCrossrefClient->method('doiIsIndexed')->willReturnMap([
            [$this->doi, true]
        ]);

        return $mockCrossrefClient;
    }

    private function createEventLog(string $date, string $message)
    {
        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
        $entry = $submissionEventLogDao->newDataObject();
        $entry->setData('assocId', $this->submissionId);
        $entry->setData('assocType', ASSOC_TYPE_SUBMISSION);
        $entry->setData('dateLogged', $date);
        $entry->setData('message', $message);

        $submissionEventLogDao->insertObject($entry);
    }

    public function testValidateEndorsementSending(): void
    {
        $validateResult = $this->endorsementService->validateEndorsementSending($this->publication);
        $this->assertEquals('plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.emptyDoi', $validateResult);

        $this->publication->setData('pub-id::doi', $this->doi);
        $validateResult = $this->endorsementService->validateEndorsementSending($this->publication);
        $this->assertEquals('plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.doiNotDeposited', $validateResult);

        $mockCrossrefClient = $this->getMockCrossrefClient();
        $this->endorsementService->setCrossrefClient($mockCrossrefClient);
        $validateResult = $this->endorsementService->validateEndorsementSending($this->publication);
        $this->assertEquals('plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.secretKey', $validateResult);

        $this->plugin->updateSetting($this->contextId, 'plauditAPISecret', $this->secretKey);
        $validateResult = $this->endorsementService->validateEndorsementSending($this->publication);
        $this->assertEquals('ok', $validateResult);
    }

    public function testMessageWasAlreadyLoggedToday(): void
    {
        $today = Core::getCurrentDate();
        $message = 'common.ok';
        $this->createEventLog($today, $message);

        $alreadyLoggedToday = $this->endorsementService->messageWasAlreadyLoggedToday($this->submissionId, $message);
        $this->assertTrue($alreadyLoggedToday);
    }

    public function testDatesAreOnSameDay(): void
    {
        $dateA = '2023-09-01 15:15:00';
        $dateB = '2023-09-01 03:00:00';
        $dateC = '2023-08-31 21:00:00';

        $this->assertTrue($this->endorsementService->datesAreOnSameDay($dateA, $dateB));
        $this->assertFalse($this->endorsementService->datesAreOnSameDay($dateA, $dateC));
        $this->assertFalse($this->endorsementService->datesAreOnSameDay($dateB, $dateC));
    }
}
