<?php

import('classes.publication.Publication');
import('plugins.generic.plauditPreEndorsement.classes.PlauditClient');
import('plugins.generic.plauditPreEndorsement.tests.TestResponse');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');

use PHPUnit\Framework\TestCase;

final class PlauditClientTest extends TestCase
{
    private $publication;
    private $plauditClient;
    private $doi = '10.1590/LepidusPreprints.1535';
    private $orcid = '0000-0001-5542-5100';
    private $orcidX = '0000-0001-5542-510X';

    public function setUp(): void
    {
        parent::setUp();
        $this->publication = new Publication();
        $this->publication->setData('pub-id::doi', $this->doi);
        $this->publication->setData('endorserOrcid', $this->orcid);

        $this->plauditClient = new PlauditClient();
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

        $this->assertEquals(ENDORSEMENT_STATUS_COMPLETED, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication));
    }

    public function testEndorsementStatusWhenRequestSucceedButDataDiffs(): void
    {
        $statusOk = 200;
        $bodyJson = "{\"endorsements\":[{\"doi\":\"10.1590/lepiduspreprints.2022\",\"orcid\":\"$this->orcid\",\"tags\":[]}]}";
        $response = new TestResponse($statusOk, $bodyJson);

        $this->assertEquals(ENDORSEMENT_STATUS_COULDNT_COMPLETE, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication));

        $lowerCaseDoi = strtolower($this->doi);
        $bodyJson = "{\"endorsements\":[{\"doi\":\"$lowerCaseDoi\",\"orcid\":\"0000-0001-5542-1234\",\"tags\":[]}]}";
        $response = new TestResponse($statusOk, $bodyJson);

        $this->assertEquals(ENDORSEMENT_STATUS_COULDNT_COMPLETE, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication));
    }

    public function testEndorsementStatusWhenRequestFails(): void
    {
        $statusBadRequest = 400;
        $bodyJson = "";
        $response = new TestResponse($statusBadRequest, $bodyJson);

        $this->assertEquals(ENDORSEMENT_STATUS_COULDNT_COMPLETE, $this->plauditClient->getEndorsementStatusByResponse($response, $this->publication));
    }
}
