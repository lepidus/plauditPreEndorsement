<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

class EndorsementStatus
{
    public const NOT_CONFIRMED = 0;
    public const CONFIRMED = 1;
    public const DENIED = 2;
    public const COMPLETED = 3;
    public const COULDNT_COMPLETE = 4;
}
