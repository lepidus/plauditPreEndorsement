<?php

use APP\publication\Publication;
use APP\core\Request;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\PlauditPreEndorsementHandler;
use PHPUnit\Framework\TestCase;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;

final class PlauditPreEndorsementHandlerTest extends TestCase
{
    private $publication;
    private $firstEndorsement;
    private $secondEndorsement;
    private $endorsementEmailToken;

    public function setUp(): void
    {
        parent::setUp();
        $this->endorsementEmailToken = md5(microtime() . 'dummy@mailinator.com.br');
        $this->publication = $this->createPublication();
        [$this->firstEndorsement, $this->secondEndorsement] = $this->addEndorsements();
    }

    private function createPublication(): Publication
    {
        $this->publication = new Publication();
        $this->publication->setData('id', rand());

        return $this->publication;
    }

    private function addEndorsements(): array
    {
        $firstEndorsementParams = [
            'name' => 'YvesDummy',
            'email' => 'dummy@mailinator.com.br',
            'emailToken' => $this->endorsementEmailToken
        ];
        $secondEndorsementParams = [
            'name' => 'JhonDummy',
            'email' => 'dummy2@mailinator.com.br',
            'emailToken' => md5(microtime() . 'dummy2@mailinator.com.br')
        ];
        $firstEndorsement = Repo::endorsement()->newDataObject($firstEndorsementParams);
        $secondEndorsement = Repo::endorsement()->newDataObject($secondEndorsementParams);

        return [$firstEndorsement, $secondEndorsement];
    }

    private function verifyEndorsementAuth($token, $endorsement, $error = null): string
    {
        $request = new Request();
        $request->_requestVars = [
            'state' => $this->publication->getId(),
            'token' => $token,
            'endorsementId' => rand()
        ];

        if ($error) {
            $request->_requestVars['error'] = $error;
        }

        $handler = new PlauditPreEndorsementHandler();
        return $handler->getStatusAuthentication($endorsement, $request);
    }

    public function testEndorsementAuthenticatesCorrectly(): void
    {
        $result = $this->verifyEndorsementAuth($this->endorsementEmailToken, $this->firstEndorsement);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_SUCCESS, $result);
    }

    public function testEndorsementTokenIsDifferent(): void
    {
        $result = $this->verifyEndorsementAuth($this->endorsementEmailToken, $this->secondEndorsement);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $result);
    }

    public function testEndorsementAutheticationHasAccessDenied(): void
    {
        $result = $this->verifyEndorsementAuth($this->endorsementEmailToken, $this->firstEndorsement, 'access_denied');
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_ACCESS_DENIED, $result);
    }
}
