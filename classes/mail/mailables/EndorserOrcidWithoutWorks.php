<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\traits\Configurable;

class EndorserOrcidWithoutWorks extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.endorserOrcidWithoutWorks.name';
    protected static ?string $description = 'emails.endorserOrcidWithoutWorks.description';
    protected static ?string $emailTemplateKey = 'ENDORSER_ORCID_WITHOUT_WORKS';

    public function __construct(Server $context, Submission $submission, array $variables)
    {
        parent::__construct([$context, $submission]);
        $this->addData($variables);
    }
}
