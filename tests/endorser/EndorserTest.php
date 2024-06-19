<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Endorser;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;

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

    public function testEndorserStatusRetrieval()
    {
        $endorser = new Endorser();
        $endorser->setStatus(Endorsement::STATUS_COMPLETED);
        $this->assertEquals($endorser->getStatus(), Endorsement::STATUS_COMPLETED);
    }

    public function testEndorserOrcidRetrieval()
    {
        $endorser = new Endorser();
        $dummyOrcid = "0009-0009-190X-Y612";
        $endorser->setOrcid($dummyOrcid);
        $this->assertEquals($endorser->getOrcid(), $dummyOrcid);
    }
}
