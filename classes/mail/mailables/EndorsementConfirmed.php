<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\traits\Configurable;

class EndorsementConfirmed extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.endorsementConfirmed.name';
    protected static ?string $description = 'emails.endorsementConfirmed.description';
    protected static ?string $emailTemplateKey = 'ENDORSEMENT_CONFIRMED';

    public function __construct(Server $context, Submission $submission, array $variables)
    {
        parent::__construct([$context, $submission]);
        $this->addData($variables);
    }
}
