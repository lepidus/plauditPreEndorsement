<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests\endorsement;

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;

class EndorsementTest extends TestCase
{
    private $endorsement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->endorsement = new Endorsement();
    }

    public function testEndorsementContextIdRetrieval()
    {
        $contextId = rand();
        $this->endorsement->setContextId($contextId);
        $this->assertEquals($this->endorsement->getContextId(), $contextId);
    }

    public function testEndorsementPublicationIdRetrieval()
    {
        $publicationId = rand();
        $this->endorsement->setPublicationId($publicationId);
        $this->assertEquals($this->endorsement->getPublicationId(), $publicationId);
    }

    public function testEndorsementNameRetrieval()
    {
        $this->endorsement->setName("DummyEndorsement");
        $this->assertEquals($this->endorsement->getName(), "DummyEndorsement");
    }

    public function testEndorsementEmailRetrieval()
    {
        $this->endorsement->setEmail("DummyEndorsement@mailinator.com.br");
        $this->assertEquals($this->endorsement->getEmail(), "DummyEndorsement@mailinator.com.br");
    }

    public function testEndorsementStatusRetrieval()
    {
        $this->endorsement->setStatus(EndorsementStatus::COMPLETED);
        $this->assertEquals($this->endorsement->getStatus(), EndorsementStatus::COMPLETED);
    }

    public function testEndorsementOrcidRetrieval()
    {
        $dummyOrcid = "0009-0009-190X-Y612";
        $this->endorsement->setOrcid($dummyOrcid);
        $this->assertEquals($this->endorsement->getOrcid(), $dummyOrcid);
    }

    public function testEndorsementEmailTokenRetrieval()
    {
        $dummyEmailToken = "066235YTVa78273grv76ha8%¨$#@aiusd";
        $this->endorsement->setEmailToken($dummyEmailToken);
        $this->assertEquals($this->endorsement->getEmailToken(), $dummyEmailToken);
    }

    public function testEndorsementEmailCountRetrieval()
    {
        $emailCount = 2;
        $this->endorsement->setEmailCount($emailCount);
        $this->assertEquals($this->endorsement->getEmailCount(), $emailCount);
    }
}
