<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use APP\handler\Handler;
use APP\submission\Submission;
use PKP\plugins\PluginRegistry;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\{
    EndorsementConfirmedEmailBuilder,
    EndorsementDeclinedEmailBuilder,
    OrcidWithoutWorksEmailBuilder
};
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;

class PlauditPreEndorsementHandler extends Handler
{
    public const AUTH_SUCCESS = 'success';
    public const AUTH_INVALID_TOKEN = 'invalid_token';
    public const AUTH_ACCESS_DENIED = 'access_denied';

    public function orcidVerify($args, $request)
    {
        $publication = Repo::publication()->get($request->getUserVar('state'));
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $endorsement = Repo::endorsement()->get($request->getUserVar('endorsementId'));

        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $contextId = $request->getContext()->getId();

        $statusAuth = $this->getStatusAuthentication($endorsement, $request);
        if ($statusAuth == self::AUTH_INVALID_TOKEN) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'orcidVerify', 'plugins.generic.plauditPreEndorsement.log.invalidToken', ['errorType' => 'invalidToken']);
            return;
        } elseif ($statusAuth == self::AUTH_ACCESS_DENIED) {
            $this->setDeclinedEndorsement($endorsement);
            $this->logMessageAndDisplayTemplate($submission, $request, 'orcidVerify', 'plugins.generic.plauditPreEndorsement.log.orcidAccessDenied', ['errorType' => 'denied']);
            return;
        }

        try {
            $code = $request->getUserVar('code');
            $orcidClient = new OrcidClient($plugin, $contextId);
            $orcid = $orcidClient->requestOrcid($code);
        } catch (\GuzzleHttp\Exception\RequestException  $exception) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'orcidVerify', 'plugins.generic.plauditPreEndorsement.log.orcidRequestError', ['errorType' => 'failure', 'orcidAPIError' => $exception->getMessage()]);
            return;
        }

        $isSandBox = $plugin->getSetting($contextId, 'orcidAPIPath') == OrcidClient::ORCID_API_URL_MEMBER_SANDBOX ||
            $plugin->getSetting($contextId, 'orcidAPIPath') == OrcidClient::ORCID_API_URL_PUBLIC_SANDBOX;
        $orcidUri = ($isSandBox ? OrcidClient::ORCID_URL_SANDBOX : OrcidClient::ORCID_URL) . $orcid;

        if (strlen($orcid) > 0) {
            $endorsementService = new EndorsementService($contextId, $plugin);

            if (!$endorsementService->checkEndorserHasWorksListed($orcid)) {
                $this->logMessageAndDisplayTemplate($submission, $request, 'orcidVerify', 'plugins.generic.plauditPreEndorsement.log.endorserOrcidWithoutWorks', ['errorType' => 'emptyWorks']);
                $this->sendEmail(new OrcidWithoutWorksEmailBuilder(), $submission, $publication, $endorsement);
                return;
            }

            if ($this->checkDataIsFromAnyAuthor($publication, 'orcid', $orcidUri)) {
                $this->logMessageAndDisplayTemplate($submission, $request, 'orcidVerify', 'plugins.generic.plauditPreEndorsement.log.endorserOrcidFromAuthor', ['errorType' => 'orcidFromAuthor']);
                return;
            }

            $endorsementService->updateEndorsementNameFromOrcid($endorsement, $orcid);

            $this->setConfirmedEndorsement($endorsement, $orcidUri);
            $this->sendEmail(new EndorsementConfirmedEmailBuilder(), $submission, $publication, $endorsement);
            $this->logMessageAndDisplayTemplate($submission, $request, 'orcidVerify', 'plugins.generic.plauditPreEndorsement.log.endorsementConfirmed', ['orcid' => $orcidUri]);

            if ($publication->getData('status') == Submission::STATUS_PUBLISHED) {
                $endorsementService->sendEndorsement($publication);
            }
        }
    }

    public function declineEndorsement($args, $request)
    {
        $publication = Repo::publication()->get($request->getUserVar('state'));
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $endorsement = Repo::endorsement()->get($request->getUserVar('endorsementId'));

        if ($this->getStatusAuthentication($endorsement, $request) == self::AUTH_INVALID_TOKEN) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'endorsementDeclined', 'plugins.generic.plauditPreEndorsement.log.invalidToken.decline', ['errorType' => 'invalidToken']);
            return;
        }

        $this->setDeclinedEndorsement($endorsement);
        $this->sendEmail(new EndorsementDeclinedEmailBuilder(), $submission, $publication, $endorsement);
        $this->logMessageAndDisplayTemplate($submission, $request, 'endorsementDeclined', 'plugins.generic.plauditPreEndorsement.log.endorsementDeclined');
    }

    private function logMessageAndDisplayTemplate($submission, $request, $template, $message, $data = [])
    {
        $context = $request->getContext();
        $data['contactEmail'] = $context->getData('contactEmail');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $templatePath = $plugin->getTemplateResource("handler/$template.tpl");

        $plugin->writeOnActivityLog($submission->getId(), $message, $data);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign($data);
        $templateMgr->display($templatePath);
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

    private function setConfirmedEndorsement($endorsement, $orcidUri)
    {
        $endorsement->setEmailToken(null);
        $endorsement->setOrcid($orcidUri);
        $endorsement->setStatus(Endorsement::STATUS_CONFIRMED);
        Repo::endorsement()->edit($endorsement, []);
    }

    private function setDeclinedEndorsement($endorsement)
    {
        $endorsement->setEmailToken(null);
        $endorsement->setStatus(Endorsement::STATUS_DENIED);
        Repo::endorsement()->edit($endorsement, []);
    }

    private function sendEmail($emailBuilder, $submission, $publication, $endorsement)
    {
        $email = $emailBuilder
            ->setEndorsement($endorsement)
            ->setPublication($publication)
            ->buildEmailParams()
            ->build(['submission' => $submission]);

        Mail::send($email);
    }

    public function getStatusAuthentication($endorsement, $request)
    {
        if ($request->getUserVar('token') != $endorsement->getEmailToken()) {
            return self::AUTH_INVALID_TOKEN;
        } elseif ($request->getUserVar('error') == 'access_denied') {
            return self::AUTH_ACCESS_DENIED;
        } else {
            return self::AUTH_SUCCESS;
        }
    }
}
