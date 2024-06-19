<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Endorser;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;

class EndorserTest extends TestCase
{
    private $endorser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->endorser = new Endorser();
    }

    public function testEndorserPublicationIdRetrieval()
    {
        $publicationId = rand();
        $this->endorser->setPublicationId($publicationId);
        $this->assertEquals($this->endorser->getPublicationId(), $publicationId);
    }

    public function testEndorserNameRetrieval()
    {
        $this->endorser->setName("DummyEndorser");
        $this->assertEquals($this->endorser->getName(), "DummyEndorser");
    }

    public function testEndorserEmailRetrieval()
    {
        $this->endorser->setEmail("DummyEndorser@mailinator.com.br");
        $this->assertEquals($this->endorser->getEmail(), "DummyEndorser@mailinator.com.br");
    }

    public function testEndorserStatusRetrieval()
    {
        $this->endorser->setStatus(Endorsement::STATUS_COMPLETED);
        $this->assertEquals($this->endorser->getStatus(), Endorsement::STATUS_COMPLETED);
    }

    public function testEndorserOrcidRetrieval()
    {
        $dummyOrcid = "0009-0009-190X-Y612";
        $this->endorser->setOrcid($dummyOrcid);
        $this->assertEquals($this->endorser->getOrcid(), $dummyOrcid);
    }

    public function testEndorserEmailTokenRetrieval()
    {
        $dummyEmailToken = "066235YTVa78273grv76ha8%¨$#@aiusd";
        $this->endorser->setEmailToken($dummyEmailToken);
        $this->assertEquals($this->endorser->getEmailToken(), $dummyEmailToken);
    }

    public function testEndorserEmailCountRetrieval()
    {
        $emailCount = 2;
        $this->endorser->setEmailCount($emailCount);
        $this->assertEquals($this->endorser->getEmailCount(), $emailCount);
    }
}
