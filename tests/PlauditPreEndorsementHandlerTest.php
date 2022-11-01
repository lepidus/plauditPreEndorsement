<?php

import('lib.pkp.tests.DatabaseTestCase');
import('classes.journal.Journal');
import('classes.publication.Publication');
import('classes.core.Request');
import('plugins.generic.plauditPreEndorsement.controllers.PlauditPreEndorsementHandler');

final class PlauditPreEndorsementHandlerTest extends DatabaseTestCase
{
    private $submissionId = 1;
    private $publicationId;
    private $endorserEmail = 'endorser@email.com';
    private $endorserName = 'Endorser';
    private $endorserToken;

    public function setUp(): void
    {
        parent::setUp();
        $this->endorserToken = md5(microtime() . $this->endorserEmail);
        $this->publicationId = $this->createPublication();
    }

    protected function getAffectedTables()
    {
        return array("publications", "publication_settings");
    }

    private function createPublication(): int
    {
        $this->publication = new Publication();
        $this->publication->setData('submissionId', $this->submissionId);
        $this->publication->setData('endorserEmail', $this->endorserEmail);
        $this->publication->setData('endorserName', $this->endorserName);
        $this->publication->setData('endorserToken', $this->endorserToken);
        $this->publication->setData('confirmedEndorsement', false);

        return DAOregistry::getDAO('PublicationDAO')->insertObject($this->publication);
    }

    private function verifyEndorserAuth($token, $error = null)
    {
        $request = new Request();
        $request->_requestVars = [
            'state' => $this->publicationId,
            'token' => $token
        ];

        if($error) {
            $request->_requestVars['error'] = $error;
        }

        $handler = new PlauditPreEndorsementHandler();
        $resultAuth = $handler->getStatusAuthentication($request);

        if($resultAuth == "success") {
            $handler->saveEndorsementeConfirmation($request);
        }

        return $resultAuth;
    }

    public function testEndorserAuthenticatesCorrectly(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserToken);
        $this->assertEquals("success", $result);
        
        $publicationFromDatabase = DAOregistry::getDAO('PublicationDAO')->getById($publicationId);
        $this->assertTrue($publicationFromDatabase->getData('confirmedEndorsement'));
    }

    public function testEndorserTokenIsDifferent(): void
    {
        $diffToken = md5(microtime() . 'email@email.com');
        $result = $this->verifyEndorserAuth($diffToken);
        $this->assertEquals("invalid_token", $result);
        
        $publicationFromDatabase = DAOregistry::getDAO('PublicationDAO')->getById($publicationId);
        $this->assertFalse($publicationFromDatabase->getData('confirmedEndorsement'));
    }

    public function testEndorserAutheticationHasAccessDenied(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserToken, 'access_denied');
        $this->assertEquals("access_denied", $result);

        $publicationFromDatabase = DAOregistry::getDAO('PublicationDAO')->getById($publicationId);
        $this->assertFalse($publicationFromDatabase->getData('confirmedEndorsement'));
    }
}