<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use APP\handler\Handler;
use APP\publication\Publication;
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
        $requestedEndorsement = $this->getRequestedEndorsement($request);
        if (is_null($requestedEndorsement)) {
            $this->displayTemplate($request, 'orcidVerify', ['errorType' => 'invalidToken']);
            return;
        }
        [$publication, $submission, $endorsement] = $requestedEndorsement;

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
        } catch (\InvalidArgumentException $exception) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'orcidVerify', 'plugins.generic.plauditPreEndorsement.log.orcidRequestError', ['errorType' => 'failure']);
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
        $requestedEndorsement = $this->getRequestedEndorsement($request);
        if (is_null($requestedEndorsement)) {
            $this->displayTemplate($request, 'endorsementDeclined', ['errorType' => 'invalidToken']);
            return;
        }
        [$publication, $submission, $endorsement] = $requestedEndorsement;

        if ($this->getStatusAuthentication($endorsement, $request) == self::AUTH_INVALID_TOKEN) {
            $this->logMessageAndDisplayTemplate($submission, $request, 'endorsementDeclined', 'plugins.generic.plauditPreEndorsement.log.invalidToken.decline', ['errorType' => 'invalidToken']);
            return;
        }

        $this->setDeclinedEndorsement($endorsement);
        $this->sendEmail(new EndorsementDeclinedEmailBuilder(), $submission, $publication, $endorsement);
        $this->logMessageAndDisplayTemplate($submission, $request, 'endorsementDeclined', 'plugins.generic.plauditPreEndorsement.log.endorsementDeclined');
    }

    /**
     * Loads the publication ("state") and the endorsement ("endorsementId")
     * of an endorser link, returning null unless both exist and are bound
     * to the same submission in the request context.
     */
    private function getRequestedEndorsement($request): ?array
    {
        $publicationId = $request->getUserVar('state');
        $endorsementId = $request->getUserVar('endorsementId');
        if (!self::isNumericId($publicationId) || !self::isNumericId($endorsementId)) {
            return null;
        }

        $contextId = (int) $request->getContext()->getId();
        $publication = Repo::publication()->get((int) $publicationId);
        $endorsement = Repo::endorsement()->get((int) $endorsementId, $contextId);
        if (is_null($publication) || is_null($endorsement)) {
            return null;
        }

        $endorsementPublication = Repo::publication()->get($endorsement->getPublicationId());
        $submission = Repo::submission()->get((int) $publication->getData('submissionId'));

        if (!self::endorsementMatchesPublication($endorsement, $publication, $endorsementPublication, $submission, $contextId)) {
            return null;
        }

        return [$publication, $submission, $endorsement];
    }

    private static function isNumericId($value): bool
    {
        return is_string($value) && ctype_digit($value);
    }

    /**
     * The endorser link may point to a newer version of the publication the
     * endorsement was created for, so both publications must belong to the
     * same submission, which must belong to the request context.
     */
    public static function endorsementMatchesPublication(
        ?Endorsement $endorsement,
        ?Publication $publication,
        ?Publication $endorsementPublication,
        ?Submission $submission,
        int $contextId
    ): bool {
        if (is_null($endorsement) || is_null($publication) || is_null($endorsementPublication) || is_null($submission)) {
            return false;
        }

        return (int) $endorsement->getContextId() === $contextId
            && (int) $endorsementPublication->getId() === (int) $endorsement->getPublicationId()
            && (int) $publication->getData('submissionId') === (int) $submission->getId()
            && (int) $endorsementPublication->getData('submissionId') === (int) $submission->getId()
            && (int) $submission->getData('contextId') === $contextId;
    }

    private function logMessageAndDisplayTemplate($submission, $request, $template, $message, $data = [])
    {
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $plugin->writeOnActivityLog($submission->getId(), $message, $data);
        $this->displayTemplate($request, $template, $data);
    }

    private function displayTemplate($request, $template, $data = [])
    {
        $context = $request->getContext();
        $data['contactEmail'] = $context->getData('contactEmail');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $templatePath = $plugin->getTemplateResource("handler/$template.tpl");

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
        if (!$this->tokenIsValid($endorsement->getEmailToken(), $request->getUserVar('token'))) {
            return self::AUTH_INVALID_TOKEN;
        } elseif ($request->getUserVar('error') == 'access_denied') {
            return self::AUTH_ACCESS_DENIED;
        } else {
            return self::AUTH_SUCCESS;
        }
    }

    private function tokenIsValid($storedToken, $receivedToken): bool
    {
        if (!is_string($storedToken) || $storedToken === '' || !is_string($receivedToken)) {
            return false;
        }

        return hash_equals($storedToken, $receivedToken);
    }
}
