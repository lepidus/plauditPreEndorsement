<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Endorser;

class EndorserTest extends TestCase
{
    public function testEndorserNameRetrieval()
    {
        $endorser = new Endorser();
        $endorser->setName("DummyEndorser");
        $this->assertEquals($endorser->getName(), "DummyEndorser");
    }

    public function testEndorserEmailRetrieval()
    {
        $endorser = new Endorser();
        $endorser->setEmail("DummyEndorser@mailinator.com.br");
        $this->assertEquals($endorser->getEmail(), "DummyEndorser@mailinator.com.br");
    }
}
