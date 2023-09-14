<?php

import('classes.publication.Publication');
import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.plauditPreEndorsement.classes.CrossrefClient');
import('plugins.generic.plauditPreEndorsement.classes.OrcidClient');
import('plugins.generic.plauditPreEndorsement.classes.EndorsementService');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');

final class EndorsementServiceTest extends DatabaseTestCase
{
    private $endorsementService;
    private $contextId = 2;
    private $submissionId = 1234;
    private $publication;
    private $plugin;
    private $doi = '10.1234/TestePublication.1234';
    private $secretKey = 'a1b2c3d4-e5f6g7h8';
    private $endorserName = 'Caio Anjo';
    private $endorserOrcid = '0010-1010-1101-0001';
    private $endorserGivenNameOrcid = 'Caio';
    private $endorserFamilyNameOrcid = 'dos Anjos';

    public function setUp(): void
    {
        parent::setUp();
        $this->publication = new Publication();
        $this->plugin = new PlauditPreEndorsementPlugin();
        $this->endorsementService = new EndorsementService($this->contextId, $this->plugin);
    }

    protected function getAffectedTables(): array
    {
        return array('event_log', 'event_log_settings', 'plugin_settings', 'publications', 'publication_settings');
    }

    private function createEndorsedPublication(): Publication
    {
        $publication = new Publication();
        $publication->setData('endorserName', $this->endorserName);
        $publication->setData('submissionId', $this->submissionId);

        $publicationId = DAORegistry::getDAO('PublicationDAO')->insertObject($publication);
        $publication->setData('id', $publicationId);

        return $publication;
    }

    private function getMockCrossrefClient()
    {
        $mockCrossrefClient = $this->createMock(CrossrefClient::class);
        $mockCrossrefClient->method('doiIsIndexed')->willReturnMap([
            [$this->doi, true]
        ]);

        return $mockCrossrefClient;
    }

    private function getMockOrcidClient()
    {
        $fictionalAccessToken = 'kjh-adf-fictional-1362m';
        $testRecord = [
            'person' => [
                'last-modified-date' => '',
                'name' => [
                    'created-date' => [
                        'value' => 1666816304613
                    ],
                    'last-modified-date' => [
                        'value' => 1666816304613
                    ],
                    'given-names' => [
                        'value' => $this->endorserGivenNameOrcid
                    ],
                    'family-name' => [
                        'value' => $this->endorserFamilyNameOrcid
                    ],
                    'credit-name' => '',
                    'source' => '',
                    'visibility' => 'public',
                    'path' => $this->endorserOrcid
                ]
            ]
        ];

        $mockOrcidClient = $this->createMock(OrcidClient::class);
        $mockOrcidClient->method('getReadPublicAccessToken')->willReturn($fictionalAccessToken);
        $mockOrcidClient->method('getOrcidRecord')->willReturnMap([
            [$this->endorserOrcid, $fictionalAccessToken, $testRecord]
        ]);
        $mockOrcidClient->method('getFullNameFromRecord')->willReturnMap([
            [$testRecord, $this->endorserGivenNameOrcid . ' ' . $this->endorserFamilyNameOrcid]
        ]);

        return $mockOrcidClient;
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
        $this->assertEquals('plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.doiNotIndexed', $validateResult);

        $mockCrossrefClient = $this->getMockCrossrefClient();
        $this->endorsementService->setCrossrefClient($mockCrossrefClient);
        $validateResult = $this->endorsementService->validateEndorsementSending($this->publication);
        $this->assertEquals('plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.secretKey', $validateResult);

        $this->plugin->updateSetting($this->contextId, 'plauditAPISecret', $this->secretKey);
        $validateResult = $this->endorsementService->validateEndorsementSending($this->publication);
        $this->assertEquals('ok', $validateResult);
    }

    public function testUpdateEndorserName(): void
    {
        $publication = $this->createEndorsedPublication();
        $mockOrcidClient = $this->getMockOrcidClient();
        $this->endorsementService->setOrcidClient($mockOrcidClient);

        $publication = $this->endorsementService->updateEndorserNameFromOrcid($publication, $this->endorserOrcid);
        $expectedNewName = $this->endorserGivenNameOrcid . ' ' . $this->endorserFamilyNameOrcid;

        $this->assertEquals($expectedNewName, $publication->getData('endorserName'));
    }

    public function testMessageWasAlreadyLoggedToday(): void
    {
        $today = Core::getCurrentDate();
        $message = 'common.ok';

        $alreadyLoggedToday = $this->endorsementService->messageWasAlreadyLoggedToday($this->submissionId, $message);
        $this->assertFalse($alreadyLoggedToday);

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
