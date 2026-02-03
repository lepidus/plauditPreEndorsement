<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\builders;

use APP\core\Application;
use APP\publication\Publication;
use PKP\plugins\PluginRegistry;
use PKP\mail\Mailable;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\MailBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables\OrcidRequestEndorserAuthorization;

class OrcidRequestMailBuilder implements MailBuilder
{
    private $endorsement;
    private $publication;
    private $mailParams;

    public function setEndorsement(Endorsement $endorsement): MailBuilder
    {
        $this->endorsement = $endorsement;
        return $this;
    }

    public function setPublication(Publication $publication): MailBuilder
    {
        $this->publication = $publication;
        return $this;
    }

    public function buildEmailParams(): MailBuilder
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

        $this->mailParams = [
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

        $mail = new OrcidRequestEndorserAuthorization($context, $submission, $this->mailParams);
        $mail->from($context->getData('contactEmail'), $context->getData('contactName'));
        $mail->to([['name' => $this->endorsement->getName(), 'email' => $this->endorsement->getEmail()]]);
        $mail->subject($emailTemplate->getLocalizedData('subject'));
        $mail->body($emailTemplate->getLocalizedData('body'));

        $this->updateEndorsement($args['endorsementChanged']);

        return $mail;
    }

    private function updateEndorsement($endorsementChanged)
    {
        if (is_null($this->endorsement->getEmailCount()) || $endorsementChanged) {
            $endorsementEmailCount = 0;
        } else {
            $endorsementEmailCount = $this->endorsement->getEmailCount();
        }

        $this->endorsement->setEmailToken($this->mailParams['endorsementEmailToken']);
        $this->endorsement->setStatus(Endorsement::STATUS_NOT_CONFIRMED);
        $this->endorsement->setEmailCount($endorsementEmailCount + 1);

        Repo::endorsement()->edit($this->endorsement, []);
    }
}
