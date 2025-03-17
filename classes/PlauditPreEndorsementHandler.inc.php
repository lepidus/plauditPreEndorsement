<?php

import('classes.handler.Handler');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');
import('plugins.generic.plauditPreEndorsement.classes.EndorsementService');
import('plugins.generic.plauditPreEndorsement.classes.OrcidClient');

define('AUTH_SUCCESS', 'success');
define('AUTH_INVALID_TOKEN', 'invalid_token');
define('AUTH_ACCESS_DENIED', 'access_denied');

class PlauditPreEndorsementHandler extends Handler
{
    public function updateEndorser($args, $request)
    {
        $plugin = new PlauditPreEndorsementPlugin();
        $submissionId = $request->getUserVar('submissionId');
        $endorserName = $request->getUserVar('endorserName');
        $endorserEmail = $request->getUserVar('endorserEmail');

        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $endorsementIsConfirmed = $publication->getData('endorsementStatus') == ENDORSEMENT_STATUS_CONFIRMED;
        if ($endorsementIsConfirmed) {
            return http_response_code(400);
        }

        if (!$plugin->inputIsEmail($endorserEmail)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['errorMessage' => __('plugins.generic.plauditPreEndorsement.endorsementEmailInvalid')]);
            return;
        }

        if ($this->checkDataIsFromAnyAuthor($publication, 'email', $endorserEmail)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['errorMessage' => __('plugins.generic.plauditPreEndorsement.endorsementFromAuthor')]);
            return;
        }

        $endorserChanged = ($endorserEmail != $publication->getData('endorserEmail'));

        $publication->setData('endorserName', $endorserName);
        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);

        $plugin->sendEmailToEndorser($publication, $endorserChanged);

        return http_response_code(200);
    }

    private function checkDataIsFromAnyAuthor($publication, $dataName, $dataValue): bool
    {
        $authors = $publication->getData('authors');

        foreach ($authors as $author) {
            if ($author->getData($dataName) == $dataValue) {
                return true;
            }
        }

        return false;
    }

    public function removeEndorsement($args, $request)
    {
        $submissionId = $request->getUserVar('submissionId');
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $endorsementFields = [
            'endorserName',
            'endorserEmail',
            'endorsementStatus',
            'endorserOrcid',
            'endorserEmailToken',
            'endorserEmailCount'
        ];

        foreach ($endorsementFields as $field) {
            $publication->unsetData($field);
        }

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);

        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $plugin->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.endorsementRemoved');

        return http_response_code(200);
    }

    public function sendEndorsementManually($args, $request)
    {
        $submissionId = $request->getUserVar('submissionId');
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');

        $endorsementStatus = $publication->getData('endorsementStatus');
        $canSendEndorsementManually = $publication->getData('status') === STATUS_PUBLISHED
            && !$plugin->userAccessingIsAuthor($submission)
            && ($endorsementStatus == ENDORSEMENT_STATUS_CONFIRMED || $endorsementStatus == ENDORSEMENT_STATUS_COULDNT_COMPLETE);

        if ($canSendEndorsementManually) {
            $endorsementService = new EndorsementService($request->getContext()->getId(), $plugin);
            $endorsementService->sendEndorsement($publication);
            return http_response_code(200);
        }

        return http_response_code(400);
    }

    public function orcidVerify($args, $request)
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->getById($request->getUserVar('state'));
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($publication->getData('submissionId'));

        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $contextId = $request->getContext()->getId();

        $statusAuth = $this->getStatusAuthentication($publication, $request);
        if ($statusAuth == AUTH_INVALID_TOKEN) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.invalidToken', ['errorType' => 'invalidToken']);
            return;
        } elseif ($statusAuth == AUTH_ACCESS_DENIED) {
            $this->setAccessDeniedEndorsement($publication);
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.orcidAccessDenied', ['errorType' => 'denied']);
            return;
        }

        try {
            $code = $request->getUserVar('code');
            $orcidClient = new OrcidClient($plugin, $contextId);
            $orcid = $orcidClient->requestOrcid($code);
        } catch (GuzzleHttp\Exception\RequestException  $exception) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.orcidRequestError', ['errorType' => 'failure', 'orcidAPIError' => $exception->getMessage()]);
            return;
        }

        $isSandBox = $plugin->getSetting($contextId, 'orcidAPIPath') == ENDORSEMENT_ORCID_API_URL_MEMBER_SANDBOX ||
            $plugin->getSetting($contextId, 'orcidAPIPath') == ENDORSEMENT_ORCID_API_URL_PUBLIC_SANDBOX;
        $orcidUri = ($isSandBox ? ENDORSEMENT_ORCID_URL_SANDBOX : ENDORSEMENT_ORCID_URL) . $orcid;

        if (strlen($orcid) > 0) {
            $endorsementService = new EndorsementService($contextId, $plugin);

            if (!$endorsementService->checkEndorserHasWorksListed($orcid)) {
                $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.endorserOrcidWithoutWorks', ['errorType' => 'emptyWorks']);
                return;
            }

            if ($this->checkDataIsFromAnyAuthor($publication, 'orcid', $orcidUri)) {
                $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.endorserOrcidFromAuthor', ['errorType' => 'orcidFromAuthor']);
                return;
            }

            $endorsementService->updateEndorserNameFromOrcid($publication, $orcid);

            $this->setConfirmedEndorsementPublication($publication, $orcidUri);
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.endorsementConfirmed', ['orcid' => $orcidUri]);

            if ($publication->getData('status') === STATUS_PUBLISHED) {
                $endorsementService->sendEndorsement($publication);
            }
        }
    }

    private function logMessageAndDisplayTemplate($submission, $request, string $message, array $data)
    {
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $templatePath = $plugin->getTemplateResource('orcidVerify.tpl');

        $plugin->writeOnActivityLog($submission, $message, $data);

        $context = $request->getContext();
        $data['contactEmail'] = $context->getData('contactEmail');

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign($data);
        $templateMgr->display($templatePath);
    }

    private function setConfirmedEndorsementPublication($publication, $orcidUri)
    {
        $publication->setData('endorserEmailToken', null);
        $publication->setData('endorserOrcid', $orcidUri);
        $publication->setData('endorsementStatus', ENDORSEMENT_STATUS_CONFIRMED);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    private function setAccessDeniedEndorsement($publication)
    {
        $publication->setData('endorserEmailToken', null);
        $publication->setData('endorsementStatus', ENDORSEMENT_STATUS_DENIED);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    public function getStatusAuthentication($publication, $request)
    {
        if ($request->getUserVar('token') != $publication->getData('endorserEmailToken')) {
            return AUTH_INVALID_TOKEN;
        } elseif ($request->getUserVar('error') == 'access_denied') {
            return AUTH_ACCESS_DENIED;
        } else {
            return AUTH_SUCCESS;
        }
    }
}
