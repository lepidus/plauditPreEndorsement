<?php

use APP\publication\Publication;
use APP\core\Request;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;
use APP\plugins\generic\plauditPreEndorsement\classes\PlauditPreEndorsementHandler;
use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Repository as EndorserRepository;

final class PlauditPreEndorsementHandlerTest extends TestCase
{
    private $publication;
    private $firstEndorser;
    private $secondEndorser;
    private $endorserEmailToken;

    public function setUp(): void
    {
        parent::setUp();
        $this->endorserEmailToken = md5(microtime() . 'dummy@mailinator.com.br');
        $this->publication = $this->createPublication();
        [$this->firstEndorser, $this->secondEndorser] = $this->addEndorsers();
    }

    private function createPublication(): Publication
    {
        $this->publication = new Publication();
        $this->publication->setData('id', rand());

        return $this->publication;
    }

    private function addEndorsers(): array
    {
        $endorserRepository = app(EndorserRepository::class);
        $firstEndorserParams = [
            'name' => 'YvesDummy',
            'email' => 'dummy@mailinator.com.br',
            'emailToken' => $this->endorserEmailToken
        ];
        $secondEndorserParams = [
            'name' => 'JhonDummy',
            'email' => 'dummy2@mailinator.com.br',
            'emailToken' => md5(microtime() . 'dummy2@mailinator.com.br')
        ];
        $firstEndorser = $endorserRepository->newDataObject($firstEndorserParams);
        $secondEndorser = $endorserRepository->newDataObject($secondEndorserParams);

        return [$firstEndorser, $secondEndorser];
    }

    private function verifyEndorserAuth($token, $endorser, $error = null): string
    {
        $request = new Request();
        $request->_requestVars = [
            'state' => $this->publication->getId(),
            'token' => $token,
            'endorserId' => rand()
        ];

        if ($error) {
            $request->_requestVars['error'] = $error;
        }

        $handler = new PlauditPreEndorsementHandler();
        return $handler->getStatusAuthentication($endorser, $request);
    }

    public function testEndorserAuthenticatesCorrectly(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken, $this->firstEndorser);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_SUCCESS, $result);
    }

    public function testEndorserTokenIsDifferent(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken, $this->secondEndorser);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $result);
    }

    public function testEndorserAutheticationHasAccessDenied(): void
    {
        $result = $this->verifyEndorserAuth($this->endorserEmailToken, $this->firstEndorser, 'access_denied');
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_ACCESS_DENIED, $result);
    }
}
