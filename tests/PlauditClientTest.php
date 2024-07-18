<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests;

use APP\publication\Publication;
use PKP\doi\Doi;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;
use APP\plugins\generic\plauditPreEndorsement\classes\PlauditClient;
use APP\plugins\generic\plauditPreEndorsement\tests\TestResponse;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use PHPUnit\Framework\TestCase;

class PlauditClientTest extends TestCase
{
    private $publication;
    private $plauditClient;
    private $doi = '10.1590/LepidusPreprints.1535';
    private $orcid = '0000-0001-5542-5100';
    private $orcidX = '0000-0001-5542-510X';
    private $endorser;

    public function setUp(): void
    {
        parent::setUp();

        $doiObject = new Doi();
        $doiObject->setData('doi', $this->doi);
        $this->publication = new Publication();
        $this->publication->setData('doiObject', $doiObject);
        $this->endorser = $this->createEndorser();

        $this->plauditClient = new PlauditClient();
    }

    private function createEndorser()
    {
        $params = [
            'name' => 'Dummy',
            'email' => 'dummy@mailinator.com.br',
            'orcid' => $this->orcid
        ];
        $endorser = Repo::endorser()->newDataObject($params);
        return $endorser;
    }

    public function testFilterOrcidNumbers(): void
    {
        $orcidUrlPrefix = 'https://orcid.org/';

        $orcidNumbers = $this->plauditClient->filterOrcidNumbers($orcidUrlPrefix . $this->orcid);
        $this->assertEquals($this->orcid, $orcidNumbers);

        $orcidNumbers = $this->plauditClient->filterOrcidNumbers($orcidUrlPrefix . $this->orcidX);
        $this->assertEquals($this->orcidX, $orcidNumbers);
    }

    public function testEndorsementStatusWhenRequestSucceed(): void
    {
        $statusOk = 200;
        $lowerCaseDoi = strtolower($this->doi);
        $bodyJson = "{\"endorsements\":[{\"doi\":\"$lowerCaseDoi\",\"orcid\":\"$this->orcid\",\"tags\":[]}]}";
        $response = new TestResponse($statusOk, $bodyJson);

        $this->assertEquals(EndorsementStatus::COMPLETED, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication, $this->endorser));
    }

    public function testEndorsementStatusWhenRequestSucceedButDataDiffs(): void
    {
        $statusOk = 200;
        $bodyJson = "{\"endorsements\":[{\"doi\":\"10.1590/lepiduspreprints.2022\",\"orcid\":\"$this->orcid\",\"tags\":[]}]}";
        $response = new TestResponse($statusOk, $bodyJson);

        $this->assertEquals(EndorsementStatus::COULDNT_COMPLETE, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication, $this->endorser));

        $lowerCaseDoi = strtolower($this->doi);
        $bodyJson = "{\"endorsements\":[{\"doi\":\"$lowerCaseDoi\",\"orcid\":\"0000-0001-5542-1234\",\"tags\":[]}]}";
        $response = new TestResponse($statusOk, $bodyJson);

        $this->assertEquals(EndorsementStatus::COULDNT_COMPLETE, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication, $this->endorser));
    }

    public function testEndorsementStatusWhenRequestFails(): void
    {
        $statusBadRequest = 400;
        $bodyJson = "";
        $response = new TestResponse($statusBadRequest, $bodyJson);

        $this->assertEquals(EndorsementStatus::COULDNT_COMPLETE, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication, $this->endorser));
    }
}
