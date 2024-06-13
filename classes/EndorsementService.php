<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use GuzzleHttp\Exception\ClientException;
use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Core;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\PlauditClient;
use APP\plugins\generic\plauditPreEndorsement\classes\CrossrefClient;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;

class EndorsementService
{
    private $plugin;
    private $contextId;
    private $orcidClient;
    private $crossrefClient;

    public function __construct($contextId, $plugin)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->crossrefClient = new CrossrefClient();
        $this->orcidClient = new OrcidClient($plugin, $contextId);
    }

    public function setCrossrefClient($crossrefClient)
    {
        $this->crossrefClient = $crossrefClient;
    }

    public function setOrcidClient($orcidClient)
    {
        $this->orcidClient = $orcidClient;
    }

    public function sendEndorsement($publication, $needCheckMessageWasLoggedToday = false)
    {
        $validationResult = $this->validateEndorsementSending($publication);

        if ($validationResult == 'ok') {
            $this->sendEndorsementToPlaudit($publication);
        } else {
            $submissionId = $publication->getData('submissionId');
            if (!$needCheckMessageWasLoggedToday or !$this->messageWasAlreadyLoggedToday($submissionId, $validationResult)) {
                $submission = Repo::submission()->get($submissionId);
                $this->plugin->writeOnActivityLog($submission, $validationResult);
            }
        }
    }

    public function validateEndorsementSending($publication): string
    {
        $doi = $publication->getDoi();
        $secretKey = $this->plugin->getSetting($this->contextId, 'plauditAPISecret');

        if (empty($doi)) {
            return 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.emptyDoi';
        }

        if (!$this->crossrefClient->doiIsIndexed($doi)) {
            return 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.doiNotIndexed';
        }

        if (empty($secretKey)) {
            return 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.secretKey';
        }

        return 'ok';
    }

    public function sendEndorsementToPlaudit($publication)
    {
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $this->plugin->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.attemptSendingEndorsement', ['doi' => $publication->getDoi(), 'orcid' => $publication->getData('endorserOrcid')]);

        $plauditClient = new PlauditClient();

        try {
            $secretKey = $this->plugin->getSetting($this->contextId, 'plauditAPISecret');
            $response = $plauditClient->requestEndorsementCreation($publication, $secretKey);
            $newEndorsementStatus = $plauditClient->getEndorsementStatusByResponse($response, $publication);
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
            $responseCode = $response->getStatusCode();
            $responseBody = print_r($response->getBody()->getContents(), true);

            $this->plugin->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.failedSendingEndorsement', ['code' => $responseCode, 'body' => $responseBody]);
            $newEndorsementStatus = Endorsement::STATUS_COULDNT_COMPLETE;
        }

        Repo::publication()->edit($publication, [
            'endorsementStatus' => $newEndorsementStatus
        ]);
    }

    public function updateEndorserNameFromOrcid($publication, $orcid)
    {
        $accessToken = $this->orcidClient->getReadPublicAccessToken();
        $orcidRecord = $this->orcidClient->getOrcidRecord($orcid, $accessToken);
        $fullName = $this->orcidClient->getFullNameFromRecord($orcidRecord);

        $publication->setData('endorserName', $fullName);
        $publicationDao = Repo::publication()->dao;
        $publicationDao->update($publication);

        return $publication;
    }

    public function messageWasAlreadyLoggedToday(int $submissionId, string $message): bool
    {
        $submissionLogEntries = Repo::eventLog()->getCollector()
            ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION, [$submissionId])
            ->getMany();
        $today = Core::getCurrentDate();

        foreach($submissionLogEntries->toArray() as $logEntry) {
            $entryWasLoggedToday = $this->datesAreOnSameDay($logEntry->getDateLogged(), $today);
            if($entryWasLoggedToday and $logEntry->getMessage() == $message) {
                return true;
            }
        }

        return false;
    }

    public function datesAreOnSameDay(string $dateA, string $dateB): bool
    {
        $datePartA = substr($dateA, 0, 10);
        $datePartB = substr($dateB, 0, 10);

        return $datePartA == $datePartB;
    }
}