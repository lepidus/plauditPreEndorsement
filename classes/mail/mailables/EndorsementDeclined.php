<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\traits\Configurable;

class EndorsementDeclined extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.endorsementDeclined.name';
    protected static ?string $description = 'emails.endorsementDeclined.description';
    protected static ?string $emailTemplateKey = 'ENDORSEMENT_DECLINED';

    public function __construct(Server $context, Submission $submission, array $variables)
    {
        parent::__construct([$context, $submission]);
        $this->addData($variables);
    }
}
