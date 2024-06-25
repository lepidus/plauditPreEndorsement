<?php

use PKP\tests\DatabaseTestCase;
use APP\submission\Submission;
use APP\publication\Publication;
use PKP\doi\Doi;
use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Core;
use APP\plugins\generic\plauditPreEndorsement\classes\CrossrefClient;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementPlugin;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Repository as EndorserRepository;
use APP\plugins\generic\plauditPreEndorsement\tests\helpers\TestHelperTrait;

final class EndorsementServiceTest extends DatabaseTestCase
{
    use TestHelperTrait;

    private $endorsementService;
    private $contextId = 1;
    private $submissionId;
    private $publication;
    private $plugin;
    private $doi = '10.1234/TestePublication.1234';
    private $secretKey = 'a1b2c3d4-e5f6g7h8';
    private $endorserName = 'Caio Anjo';
    private $endorserOrcid = '0010-1010-1101-0001';
    private $endorserGivenNameOrcid = 'Caio';
    private $endorserFamilyNameOrcid = 'dos Anjos';
    private $endorserId;

    public function setUp(): void
    {
        parent::setUp();
        $this->addSchemaFile('endorser');
        $this->publication = $this->createPublication();
        $this->plugin = new PlauditPreEndorsementPlugin();
        $this->endorsementService = new EndorsementService($this->contextId, $this->plugin);
        $this->endorserId = $this->createEndorser();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $submission = Repo::submission()->get($this->submissionId);
        Repo::submission()->delete($submission);

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->deleteSetting($this->contextId, $this->plugin->getName(), 'plauditAPISecret');
    }

    protected function getAffectedTables(): array
    {
        return ['event_log', 'event_log_settings', 'endorsers'];
    }

    private function createEndorser()
    {
        $endorserRepository = app(EndorserRepository::class);
        $params = [
            'publicationId' => $this->publication->getId(),
            'contextId' => $this->contextId,
            'name' => 'Dummy',
            'email' => 'dummy@mailinator.com.br'
        ];
        $endorser = $endorserRepository->newDataObject($params);
        return $endorserRepository->add($endorser);
    }

    private function createPublication(): Publication
    {
        $context = DAORegistry::getDAO('ServerDAO')->getById($this->contextId);

        $submission = new Submission();
        $submission->setData('contextId', $this->contextId);

        $publication = new Publication();

        $this->submissionId = Repo::submission()->add($submission, $publication, $context);

        $submission = Repo::submission()->get($this->submissionId);
        $publication = $submission->getCurrentPublication();

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
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $this->submissionId,
            'message' => $message,
            'isTranslated' => false,
            'dateLogged' => $date,
        ]);

        Repo::eventLog()->add($eventLog);
    }

    public function testValidateEndorsementSending(): void
    {
        $validateResult = $this->endorsementService->validateEndorsementSending($this->publication);
        $this->assertEquals('plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.emptyDoi', $validateResult);

        $doiObject = new Doi();
        $doiObject->setData('doi', $this->doi);
        $this->publication->setData('doiObject', $doiObject);
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
        $endorserRepository = app(EndorserRepository::class);
        $endorser = $endorserRepository->get($this->endorserId);
        $mockOrcidClient = $this->getMockOrcidClient();
        $this->endorsementService->setOrcidClient($mockOrcidClient);

        $newEndorser = $this->endorsementService->updateEndorserNameFromOrcid($endorser, $this->endorserOrcid);
        $expectedNewName = $this->endorserGivenNameOrcid . ' ' . $this->endorserFamilyNameOrcid;

        $this->assertEquals($expectedNewName, $newEndorser->getName());
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
