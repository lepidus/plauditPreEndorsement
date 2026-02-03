<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\builders;

use PKP\mail\Mailable;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

interface MailBuilder
{
    public function setEndorsement(Endorsement $endorsement): MailBuilder;
    public function buildEmailParams(): MailBuilder;
    public function build(array $args = []): Mailable;
}
