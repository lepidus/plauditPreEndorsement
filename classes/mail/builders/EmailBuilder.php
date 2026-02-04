<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\mail\builders;

use PKP\mail\Mailable;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

interface EmailBuilder
{
    public function setEndorsement(Endorsement $endorsement): EmailBuilder;
    public function buildEmailParams(): EmailBuilder;
    public function build(array $args = []): Mailable;
}
