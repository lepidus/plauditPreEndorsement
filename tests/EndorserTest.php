<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorser;

class EndorserTest extends TestCase
{
    public function testEndorserNameRetrieval()
    {
        $endorser = new Endorser("DummyEndorser");
        $this->assertEquals($endorser->getName(), "DummyEndorser");
    }

    public function testChangeEndorserName()
    {
        $endorser = new Endorser("DummyEndorser");
        $this->assertEquals($endorser->getName(), "DummyEndorser");
        $endorser->setName("Dummy Hugo De León");
        $this->assertEquals($endorser->getName(), "Dummy Hugo De León");
    }
}
