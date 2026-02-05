<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\builders;

use APP\core\Application;
use APP\publication\Publication;
use PKP\plugins\PluginRegistry;
use PKP\mail\Mailable;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\EmailBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables\OrcidRequestEndorserAuthorization;

class OrcidRequestEmailBuilder implements EmailBuilder
{
    private $endorsement;
    private $publication;
    private $emailParams;

    public function setEndorsement(Endorsement $endorsement): EmailBuilder
    {
        $this->endorsement = $endorsement;
        return $this;
    }

    public function setPublication(Publication $publication): EmailBuilder
    {
        $this->publication = $publication;
        return $this;
    }

    public function buildEmailParams(): EmailBuilder
    {
        $request = Application::get()->getRequest();
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');

        $endorsementEmailToken = md5(microtime() . $this->endorsement->getEmail());
        $orcidClient = new OrcidClient($plugin, $request->getContext()->getId());
        $redirectParams = [
            'token' => $endorsementEmailToken,
            'state' => $this->publication->getId(),
            'endorsementId' => $this->endorsement->getId()
        ];
        $oauthUrl = $orcidClient->buildOAuthUrl($redirectParams);
        $endorsementDeclineUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            null,
            $plugin::HANDLER_PAGE,
            'declineEndorsement',
            null,
            $redirectParams
        );

        $this->emailParams = [
            'endorsementEmailToken' => $endorsementEmailToken,
            'orcidOauthUrl' => $oauthUrl,
            'endorsementDeclineUrl' => $endorsementDeclineUrl,
            'endorserName' => htmlspecialchars($this->endorsement->getName()),
        ];

        return $this;
    }

    public function build(array $args = []): Mailable
    {
        $context = Application::get()->getRequest()->getContext();
        $submission = Repo::submission()->get($this->publication->getData('submissionId'));

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            'ORCID_REQUEST_ENDORSER_AUTHORIZATION'
        );

        $email = new OrcidRequestEndorserAuthorization($context, $submission, $this->emailParams);
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to([['name' => $this->endorsement->getName(), 'email' => $this->endorsement->getEmail()]]);
        $email->subject($emailTemplate->getLocalizedData('subject'));
        $email->body($emailTemplate->getLocalizedData('body'));

        $this->updateEndorsement($args['endorsementChanged']);

        return $email;
    }

    private function updateEndorsement($endorsementChanged)
    {
        if (is_null($this->endorsement->getEmailCount()) || $endorsementChanged) {
            $endorsementEmailCount = 0;
        } else {
            $endorsementEmailCount = $this->endorsement->getEmailCount();
        }

        $today = new \DateTime();
        $this->endorsement->setEmailToken($this->emailParams['endorsementEmailToken']);
        $this->endorsement->setStatus(Endorsement::STATUS_NOT_CONFIRMED);
        $this->endorsement->setEmailCount($endorsementEmailCount + 1);
        $this->endorsement->setLastEmailDate($today->format('Y-m-d'));

        Repo::endorsement()->edit($this->endorsement, []);
    }
}
