<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\facades;

use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Repository as EndorserRepository;

class Repo extends \APP\facades\Repo
{
    public static function endorser(): EndorserRepository
    {
        return app(EndorserRepository::class);
    }
}
