<?php

use GuzzleHttp\Exception\ClientException;

import('plugins.generic.plauditPreEndorsement.classes.PlauditClient');
import('plugins.generic.plauditPreEndorsement.classes.CrossrefClient');
import('plugins.generic.plauditPreEndorsement.classes.OrcidClient');

class EndorsementService
{
    private $plugin;
    private $contextId;
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
                $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
                $this->plugin->writeOnActivityLog($submission, $validationResult);
            }
        }
    }

    public function validateEndorsementSending($publication): string
    {
        $doi = $publication->getData('pub-id::doi');
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
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($publication->getData('submissionId'));
        $this->plugin->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.attemptSendingEndorsement', ['doi' => $publication->getData('pub-id::doi'), 'orcid' => $publication->getData('endorserOrcid')]);

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
            $newEndorsementStatus = ENDORSEMENT_STATUS_COULDNT_COMPLETE;
        }

        $publication->setData('endorsementStatus', $newEndorsementStatus);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    public function updateEndorserNameFromOrcid($publication, $orcid)
    {
        $accessToken = $this->orcidClient->getReadPublicAccessToken();
        $orcidRecord = $this->orcidClient->getOrcidRecord($orcid, $accessToken);
        $fullName = $this->orcidClient->getFullNameFromRecord($orcidRecord);

        $publication->setData('endorserName', $fullName);
        DAORegistry::getDAO('PublicationDAO')->updateObject($publication);

        return $publication;
    }

    public function checkEndorserHasWorksListed($orcid)
    {
        $accessToken = $this->orcidClient->getReadPublicAccessToken();
        $orcidWorks = $this->orcidClient->getOrcidWorks($orcid, $accessToken);

        return $this->orcidClient->recordHasWorks($orcidWorks);
    }

    public function messageWasAlreadyLoggedToday(int $submissionId, string $message): bool
    {
        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
        $submissionLogEntries = $submissionEventLogDao->getBySubmissionId($submissionId);
        $today = Core::getCurrentDate();

        foreach ($submissionLogEntries->toArray() as $logEntry) {
            $entryWasLoggedToday = $this->datesAreOnSameDay($logEntry->getDateLogged(), $today);
            if ($entryWasLoggedToday and $logEntry->getMessage() == $message) {
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
