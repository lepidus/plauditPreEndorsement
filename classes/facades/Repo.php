<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\facades;

use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Repository as EndorsementRepository;

class Repo extends \APP\facades\Repo
{
    public static function endorsement(): EndorsementRepository
    {
        return app(EndorsementRepository::class);
    }
}
