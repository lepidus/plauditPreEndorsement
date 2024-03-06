<?php

use APP\publication\Publication;
use APP\core\Request;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\PlauditPreEndorsementHandler;
use PHPUnit\Framework\TestCase;

final class PlauditPreEndorsementHandlerTest extends TestCase
{
    private $publication;
    private $endorserEmail = 'endorser@email.com';
    private $endorserName = 'Endorser';
    private $endorserEmailToken;

    public function setUp(): void
    {
        parent::setUp();
        $this->endorserEmailToken = md5(microtime() . $this->endorserEmail);
        $this->publication = $this->createPublication();
    }

    private function createPublication(): Publication
    {
        $this->publication = new Publication();
        $this->publication->setData('id', 1);
        $this->publication->setData('endorserEmail', $this->endorserEmail);
        $this->publication->setData('endorserName', $this->endorserName);
        $this->publication->setData('endorserEmailToken', $this->endorserEmailToken);
        $this->publication->setData('endorsementStatus', Endorsement::STATUS_NOT_CONFIRMED);

        return $this->publication;
    }

    private function verifyEndorserAuth($token, $error = null): string
    {
        $request = new Request();
        $request->_requestVars = [
            'state' => $this->publication->getId(),
            'token' => $token
        ];

        if ($error) {
            $request->_requestVars['error'] = $error;
        }

        $handler = new PlauditPreEndorsementHandler();
        return $handler->getStatusAuthentication($this->publication, $request);
    }

    public function testEndorserAuthenticatesCorrectly(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_SUCCESS, $result);
    }

    public function testEndorserTokenIsDifferent(): void
    {
        $diffToken = md5(microtime() . 'email@email.com');
        $result = $this->verifyEndorserAuth($diffToken);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $result);
    }

    public function testEndorserAutheticationHasAccessDenied(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken, 'access_denied');
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_ACCESS_DENIED, $result);
    }
}
