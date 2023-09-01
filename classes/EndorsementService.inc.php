<?php

import('plugins.generic.plauditPreEndorsement.classes.PlauditClient');

class EndorsementService
{
    private $plugin;
    private $contextId;
    private $crossrefClient;

    public function __construct($contextId, $plugin)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
    }

    public function setCrossrefClient($crossrefClient)
    {
        $this->crossrefClient = $crossrefClient;
    }

    public function validateEndorsementSending($publication): string
    {
        $doi = $publication->getData('pub-id::doi');
        $secretKey = $this->plugin->getSetting($this->contextId, 'plauditAPISecret');

        if(empty($doi)) {
            return 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.emptyDoi';
        }

        if(!$this->crossrefClient->doiIsIndexed($doi)) {
            return 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.doiNotDeposited';
        }

        if(empty($secretKey)) {
            return 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.secretKey';
        }

        return 'ok';
    }

    public function sendEndorsementToPlaudit($plugin, $contextId, $publication)
    {
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($publication->getData('submissionId'));
        $plugin->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.attemptSendingEndorsement', ['doi' => $publication->getData('pub-id::doi'), 'orcid' => $publication->getData('endorserOrcid')]);

        $plauditClient = new PlauditClient();

        try {
            $secretKey = $plugin->getSetting($contextId, 'plauditAPISecret');
            $response = $plauditClient->requestEndorsementCreation($publication, $secretKey);
            $newEndorsementStatus = $plauditClient->getEndorsementStatusByResponse($response, $publication);
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
            $responseCode = $response->getStatusCode();
            $responseBody = print_r($response->getBody()->getContents(), true);

            $plugin->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.failedSendingEndorsement', ['code' => $responseCode, 'body' => $responseBody]);
            $newEndorsementStatus = ENDORSEMENT_STATUS_COULDNT_COMPLETE;
        }

        $publication->setData('endorsementStatus', $newEndorsementStatus);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    public function messageWasAlreadyLoggedToday(int $submissionId, string $message): bool
    {
        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
        $submissionLogEntries = $submissionEventLogDao->getBySubmissionId($submissionId);
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
