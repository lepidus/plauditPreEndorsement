<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\traits\Configurable;

class OrcidRequestEndorserAuthorization extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.orcidRequestEndorserAuthorization.name';
    protected static ?string $description = 'emails.orcidRequestEndorserAuthorization.description';
    protected static ?string $emailTemplateKey = 'ORCID_REQUEST_ENDORSER_AUTHORIZATION';

    public function __construct(Server $context, Submission $submission, array $variables)
    {
        parent::__construct([$context, $submission]);
        $this->addData($variables);
    }
}
