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
        $submissionId = $args['submissionId'];
        $endorserName = $args['endorserName'];
        $endorserEmail = $args['endorserEmail'];
        
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $publication->setData('endorserName', $endorserName);
        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);

        $plugin = new PlauditPreEndorsementPlugin();
        $plugin->sendEmailToEndorser($publication);

        return http_response_code(200);
    }

    public function orcidVerify($args, $request)
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->getById($request->getUserVar('state'));

        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');

        $statusAuth = $this->getStatusAuthentication($publication, $request);
        if($statusAuth == AUTH_INVALID_TOKEN) {
            $this->logAndDisplayTemplate($request, 'PlauditPreEndorsementHandler::orcidverify - Token from auth is invalid', ['verifySuccess' => false, 'invalidToken' => true]);
			return;
        }
        else if($statusAuth == AUTH_ACCESS_DENIED) {
            $this->setAccessDeniedEndorsement($publication);
            $logErrorMsg = 'PlauditPreEndorsementHandler::orcidverify - ORCID access was denied: '. $request->getUserVar('error_description');
            $this->logAndDisplayTemplate($request, $logErrorMsg, ['verifySuccess' => false, 'denied' => true]);
			return;
        }

		try {
            $response = $this->requestOrcid($request, $plugin);
			$responseJson = json_decode($response->getBody(), true);
			$plugin->logInfo('Response body: ' . print_r($responseJson, true));
		}
        catch (GuzzleHttp\Exception\RequestException  $exception) {
            $this->logAndDisplayTemplate($request, "Publication fail:  " . $exception->getMessage(), ['orcidAPIError' => $exception->getMessage(), 'verifySuccess' => false]);
            return;
		}

        $contextId = $request->getContext()->getId();
        $isSandBox = $plugin->getSetting($contextId, 'orcidProfileAPIPath') == ORCID_API_URL_MEMBER_SANDBOX ||
			$plugin->getSetting($contextId, 'orcidProfileAPIPath') == ORCID_API_URL_PUBLIC_SANDBOX;
        $orcidUri = ($isSandBox ? ORCID_URL_SANDBOX : ORCID_URL) . $responseJson['orcid'];

		if ($response->getStatusCode() == 200 && strlen($responseJson['orcid']) > 0) {
            $this->setConfirmedEndorsementPublication($publication, $orcidUri);
            $this->logAndDisplayTemplate($request, '', ['verifySuccess' => true, 'orcid' => $orcidUri]);
        }
        else {
            $logErrorMsg = 'PlauditPreEndorsementHandler::orcidverify - Unexpected response: ' . $response->getStatusCode();
            $this->logAndDisplayTemplate($request, $logErrorMsg, ['authFailure'=> true, 'orcidAPIError' => $response->getReasonPhrase(), 'verifySuccess' => true]);
        }
    }

    private function requestOrcid($request, $plugin)
    {
        $contextId = $request->getContext()->getId();
        $orcidRequestUrl = $plugin->getSetting($contextId, 'orcidProfileAPIPath') . OAUTH_TOKEN_URL;

		$httpClient = Application::get()->getHttpClient();
		$header = ['Accept' => 'application/json'];
		$postData = [
			'code' => $request->getUserVar('code'),
			'grant_type' => 'authorization_code',
			'client_id' => $plugin->getSetting($contextId, 'orcidClientId'),
			'client_secret' => $plugin->getSetting($contextId, 'orcidClientSecret')
		];

		$plugin->logInfo('POST ' . $orcidRequestUrl);
		$plugin->logInfo('Request header: ' . var_export($header, true));
		$plugin->logInfo('Request body: ' . http_build_query($postData));
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

    private function logAndDisplayTemplate($request, string $logErrorMsg, array $dataAssign)
    {
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $templatePath = $plugin->getTemplateResource('orcidVerify.tpl');
        
        if($logErrorMsg != "") $plugin->logError($logErrorMsg);
        $templateMgr->assign($dataAssign);
        $templateMgr->display($templatePath);
    }

    private function setConfirmedEndorsementPublication($publication, $orcidUri)
    {
        $publication->setData('endorserEmailToken', null);
        $publication->setData('endorserOrcid', $orcidUri);
        $publication->setData('confirmedEndorsement', true);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    private function setAccessDeniedEndorsement($publication)
    {
        $publication->setData('endorserToken', null);
        $publication->setData('endorserAccessDenied', Core::getCurrentDate());
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    public function getStatusAuthentication($publication, $request)
    {
        if ($request->getUserVar('token') != $publication->getData('endorserEmailToken')) {
			return AUTH_INVALID_TOKEN;
		}
        else if($request->getUserVar('error') == 'access_denied'){
            return AUTH_ACCESS_DENIED;
        }
        else{
            return AUTH_SUCCESS;
        }
    }
}