<?php

use APP\publication\Publication;
use APP\core\Request;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\PlauditPreEndorsementHandler;
use PHPUnit\Framework\TestCase;

final class PlauditPreEndorsementHandlerTest extends TestCase
{
    private $publication;
    private $endorserEmailToken;

    public function setUp(): void
    {
        parent::setUp();
        $this->endorserEmailToken = md5(microtime() . 'dummy@mailinator.com.br');
        $this->publication = $this->createPublication();
        $this->addEndorsers();
    }

    private function createPublication(): Publication
    {
        $this->publication = new Publication();
        $this->publication->setData('id', rand());

        return $this->publication;
    }

    private function addEndorsers(): void
    {
        $endorsers = [
            0 => [
                'name' => 'YvesDummy',
                'email' => 'dummy@mailinator.com.br',
                'endorserEmailToken' => $this->endorserEmailToken
            ],
            1 => [
                'name' => 'JhonDummy',
                'email' => 'dummy2@mailinator.com.br',
                'endorserEmailToken' => md5(microtime() . 'dummy2@mailinator.com.br')
            ]
        ];
        $this->publication->setData('endorsers', $endorsers);
    }

    private function verifyEndorserAuth($token, $endorserIndex, $error = null): string
    {
        $request = new Request();
        $request->_requestVars = [
            'state' => $this->publication->getId(),
            'token' => $token,
            'name' => 'YvesDummy',
            'email' => 'dummy1@mailinator.com.br'
        ];

        if ($error) {
            $request->_requestVars['error'] = $error;
        }

        $endorsers = $this->publication->getData('endorsers');
        $endorser = $endorsers[$endorserIndex];

        $handler = new PlauditPreEndorsementHandler();
        return $handler->getStatusAuthentication($endorser, $request);
    }

    public function testEndorserAuthenticatesCorrectly(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken, 0);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_SUCCESS, $result);
    }

    public function testEndorserTokenIsDifferent(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken, 1);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $result);
    }

    public function testEndorserAutheticationHasAccessDenied(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken, 0, 'access_denied');
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_ACCESS_DENIED, $result);
    }
}
