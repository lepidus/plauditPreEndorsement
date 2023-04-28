<?php

import('classes.handler.Handler');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');

define('AUTH_SUCCESS', 'success');
define('AUTH_INVALID_TOKEN', 'invalid_token');
define('AUTH_ACCESS_DENIED', 'access_denied');


class PlauditPreEndorsementHandler extends Handler
{
    public function updateEndorser($args, $request)
    {
        $submissionId = $request->getUserVar('submissionId');
        $endorserName = $request->getUserVar('endorserName');
        $endorserEmail = $request->getUserVar('endorserEmail');

        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $endorsementIsConfirmed = $publication->getData('endorsementStatus') == ENDORSEMENT_STATUS_CONFIRMED;
        if ($endorsementIsConfirmed) {
            return http_response_code(400);
        }

        $endorserChanged = ($endorserEmail != $publication->getData('endorserEmail'));

        $publication->setData('endorserName', $endorserName);
        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);

        $plugin = new PlauditPreEndorsementPlugin();
        $plugin->sendEmailToEndorser($publication, $endorserChanged);

        return http_response_code(200);
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

        foreach($endorsementFields as $field) {
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

        if($canSendEndorsementManually) {
            $plugin->sendEndorsementToPlaudit($publication);
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

        $statusAuth = $this->getStatusAuthentication($publication, $request);
        if ($statusAuth == AUTH_INVALID_TOKEN) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.invalidToken', ['verifySuccess' => false, 'invalidToken' => true]);
            return;
        } elseif ($statusAuth == AUTH_ACCESS_DENIED) {
            $this->setAccessDeniedEndorsement($publication);
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.orcidAccessDenied', ['verifySuccess' => false, 'denied' => true]);
            return;
        }

        try {
            $response = $this->requestOrcid($request, $plugin);
            $responseJson = json_decode($response->getBody(), true);
        } catch (GuzzleHttp\Exception\RequestException  $exception) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.orcidRequestError', ['orcidAPIError' => $exception->getMessage(), 'verifySuccess' => false]);
            return;
        }

        $contextId = $request->getContext()->getId();
        $isSandBox = $plugin->getSetting($contextId, 'orcidAPIPath') == ENDORSEMENT_ORCID_API_URL_MEMBER_SANDBOX ||
            $plugin->getSetting($contextId, 'orcidAPIPath') == ENDORSEMENT_ORCID_API_URL_PUBLIC_SANDBOX;
        $orcidUri = ($isSandBox ? ENDORSEMENT_ORCID_URL_SANDBOX : ENDORSEMENT_ORCID_URL) . $responseJson['orcid'];

        if ($response->getStatusCode() == 200 && strlen($responseJson['orcid']) > 0) {
            $this->setConfirmedEndorsementPublication($publication, $orcidUri);
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.endorsementConfirmed', ['verifySuccess' => true, 'orcid' => $orcidUri]);
        } else {
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.orcidRequestError', ['authFailure'=> true, 'orcidAPIError' => $response->getReasonPhrase(), 'verifySuccess' => true]);
        }
    }

    private function requestOrcid($request, $plugin)
    {
        $contextId = $request->getContext()->getId();
        $orcidRequestUrl = $plugin->getSetting($contextId, 'orcidAPIPath') . OAUTH_TOKEN_URL;

        $httpClient = Application::get()->getHttpClient();
        $header = ['Accept' => 'application/json'];
        $postData = [
            'code' => $request->getUserVar('code'),
            'grant_type' => 'authorization_code',
            'client_id' => $plugin->getSetting($contextId, 'orcidClientId'),
            'client_secret' => $plugin->getSetting($contextId, 'orcidClientSecret')
        ];

        $response = $httpClient->request(
            'POST',
            $orcidRequestUrl,
            [
                'headers' => $header,
                'form_params' => $postData,
            ]
        );

        return $response;
    }

    private function logMessageAndDisplayTemplate($submission, $request, string $message, array $data)
    {
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $templatePath = $plugin->getTemplateResource('orcidVerify.tpl');

        $plugin->writeOnActivityLog($submission, $message, $data);

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
        $publication->setData('endorserToken', null);
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
