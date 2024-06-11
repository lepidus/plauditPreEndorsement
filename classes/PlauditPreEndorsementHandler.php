<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use APP\handler\Handler;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\plugins\PluginRegistry;
use APP\template\TemplateManager;
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementPlugin;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;

class PlauditPreEndorsementHandler extends Handler
{
    public const AUTH_SUCCESS = 'success';
    public const AUTH_INVALID_TOKEN = 'invalid_token';
    public const AUTH_ACCESS_DENIED = 'access_denied';

    public function updateEndorser($args, $request)
    {
        $plugin = new PlauditPreEndorsementPlugin();
        $submissionId = $request->getUserVar('submissionId');
        $endorserName = $request->getUserVar('endorserName');
        $endorserEmail = $request->getUserVar('endorserEmail');

        $submission = Repo::submission()->get($submissionId);
        $publication = $submission->getCurrentPublication();

        $endorsementIsConfirmed = $publication->getData('endorsementStatus') == Endorsement::STATUS_CONFIRMED;
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
        Repo::publication()->edit($publication, []);

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
        $submission = Repo::submission()->get($submissionId);
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

        Repo::publication()->edit($publication, []);

        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $plugin->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.endorsementRemoved');

        return http_response_code(200);
    }

    public function sendEndorsementManually($args, $request)
    {
        $submissionId = $request->getUserVar('submissionId');
        $submission = Repo::submission()->get($submissionId);
        $publication = $submission->getCurrentPublication();
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');

        $endorsementStatus = $publication->getData('endorsementStatus');
        $canSendEndorsementManually = $publication->getData('status') == Submission::STATUS_PUBLISHED
            && !$plugin->userAccessingIsAuthor($submission)
            && ($endorsementStatus == Endorsement::STATUS_CONFIRMED || $endorsementStatus == Endorsement::STATUS_COULDNT_COMPLETE);

        if ($canSendEndorsementManually) {
            $endorsementService = new EndorsementService($request->getContext()->getId(), $plugin);
            $endorsementService->sendEndorsement($publication);
            return http_response_code(200);
        }

        return http_response_code(400);
    }

    public function orcidVerify($args, $request)
    {
        $publication = Repo::publication()->get($request->getUserVar('state'));
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $endorsers = $publication->getData('endorsers');
        $index = $this->filterByNameAndEmail($endorsers, $request->getUserVar('name'), $request->getUserVar('email'));

        $endorser = $endorsers[$index];
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $contextId = $request->getContext()->getId();

        $statusAuth = $this->getStatusAuthentication($endorser, $request);
        if ($statusAuth == self::AUTH_INVALID_TOKEN) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.invalidToken', ['errorType' => 'invalidToken']);
            return;
        } elseif ($statusAuth == self::AUTH_ACCESS_DENIED) {
            $this->setAccessDeniedEndorsement($publication);
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.orcidAccessDenied', ['errorType' => 'denied']);
            return;
        }

        try {
            $code = $request->getUserVar('code');
            $orcidClient = new OrcidClient($plugin, $contextId);
            $orcid = $orcidClient->requestOrcid($code);
        } catch (\GuzzleHttp\Exception\RequestException  $exception) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.orcidRequestError', ['errorType' => 'failure', 'orcidAPIError' => $exception->getMessage()]);
            return;
        }

        $isSandBox = $plugin->getSetting($contextId, 'orcidAPIPath') == OrcidClient::ORCID_API_URL_MEMBER_SANDBOX ||
            $plugin->getSetting($contextId, 'orcidAPIPath') == OrcidClient::ORCID_API_URL_PUBLIC_SANDBOX;
        $orcidUri = ($isSandBox ? OrcidClient::ORCID_URL_SANDBOX : OrcidClient::ORCID_URL) . $orcid;

        if (strlen($orcid) > 0) {
            if ($this->checkDataIsFromAnyAuthor($publication, 'orcid', $orcidUri)) {
                $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.endorserOrcidFromAuthor', ['errorType' => 'orcidFromAuthor']);
                return;
            }

            $endorsementService = new EndorsementService($contextId, $plugin);
            $endorsementService->updateEndorserNameFromOrcid($publication, $orcid);

            $this->setConfirmedEndorsementPublication($publication, $index, $orcidUri);
            $this->logMessageAndDisplayTemplate($submission, $request, 'plugins.generic.plauditPreEndorsement.log.endorsementConfirmed', ['orcid' => $orcidUri]);

            if ($publication->getData('status') == Submission::STATUS_PUBLISHED) {
                $endorsementService->sendEndorsement($publication);
            }
        }
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

    private function setConfirmedEndorsementPublication($publication, $index, $orcidUri)
    {
        $endorsers = $publication->getData('endorsers');
        $endorsers[$index]['endorserEmailToken'] = null;
        $endorsers[$index]['endorserOrcid'] = $orcidUri;
        $endorsers[$index]['endorsementStatus'] = Endorsement::STATUS_CONFIRMED;
        Repo::publication()->edit($publication, ['endorsers' => $endorsers]);
    }

    private function setAccessDeniedEndorsement($publication)
    {
        $publication->setData('endorserEmailToken', null);
        $publication->setData('endorsementStatus', Endorsement::STATUS_DENIED);
        Repo::publication()->edit($publication, []);
    }

    public function getStatusAuthentication($endorser, $request)
    {
        if ($request->getUserVar('token') != $endorser['endorserEmailToken']) {
            return self::AUTH_INVALID_TOKEN;
        } elseif ($request->getUserVar('error') == 'access_denied') {
            return self::AUTH_ACCESS_DENIED;
        } else {
            return self::AUTH_SUCCESS;
        }
    }

    private function filterByNameAndEmail(array $endorsers, string $name, string $email)
    {
        return array_keys(array_filter($endorsers, function ($endorser) use ($name, $email) {
            return isset($endorser['name']) && isset($endorser['email']) &&
                   $endorser['name'] == $name && $endorser['email'] == $email;
        }))[0];
    }
}
