<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\builders;

use APP\core\Application;
use APP\publication\Publication;
use PKP\mail\Mailable;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\EmailBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables\EndorsementConfirmed;

class EndorsementConfirmedEmailBuilder implements EmailBuilder
{
    private $endorsement;
    private $publication;
    private $primaryAuthor;
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
        $this->primaryAuthor = $this->publication->getPrimaryAuthor();
        if (!isset($this->primaryAuthor)) {
            $authors = $this->publication->getData('authors');
            $this->primaryAuthor = $authors->first();
        }

        $this->emailParams = [
            'authorName' => htmlspecialchars($this->primaryAuthor->getFullName()),
            'endorserName' => htmlspecialchars($this->endorsement->getName()),
            'endorserOrcid' => htmlspecialchars($this->endorsement->getOrcid())
        ];

        return $this;
    }

    public function build(array $args = []): Mailable
    {
        $submission = $args['submission'];
        $context = Application::get()->getContextDAO()->getById($submission->getData('contextId'));

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            'ENDORSEMENT_CONFIRMED'
        );

        $email = new EndorsementConfirmed($context, $submission, $this->emailParams);
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to([
            ['name' => $this->emailParams['endorserName'], 'email' => $this->endorsement->getEmail()],
            ['name' => $this->primaryAuthor->getFullName(), 'email' => $this->primaryAuthor->getEmail()]
        ]);
        $email->subject($emailTemplate->getLocalizedData('subject'));
        $email->body($emailTemplate->getLocalizedData('body'));

        return $email;
    }
}
