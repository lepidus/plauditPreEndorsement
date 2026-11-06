<?php

use APP\publication\Publication;
use APP\submission\Submission;
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

    public function testEndorsementWithoutTokenIsRejectedWhenStoredTokenIsNull(): void
    {
        $this->firstEndorsement->setEmailToken(null);

        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $this->verifyEndorsementAuth(null, $this->firstEndorsement));
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $this->verifyEndorsementAuth('', $this->firstEndorsement));
    }

    public function testEndorsementWithEmptyTokenIsRejectedWhenStoredTokenIsEmpty(): void
    {
        $this->firstEndorsement->setEmailToken('');

        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $this->verifyEndorsementAuth('', $this->firstEndorsement));
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $this->verifyEndorsementAuth(null, $this->firstEndorsement));
    }

    public function testEndorsementTokenMustBeAString(): void
    {
        $result = $this->verifyEndorsementAuth([$this->endorsementEmailToken], $this->firstEndorsement);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $result);
    }

    public function testEndorsementTokenIsComparedStrictly(): void
    {
        $this->firstEndorsement->setEmailToken('0e462097431906509019562988736854');

        $result = $this->verifyEndorsementAuth('0e830400451993494058024219903391', $this->firstEndorsement);
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $result);
    }

    public function testDeniedAccessWithoutValidTokenIsRejected(): void
    {
        $this->firstEndorsement->setEmailToken(null);

        $result = $this->verifyEndorsementAuth(null, $this->firstEndorsement, 'access_denied');
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_INVALID_TOKEN, $result);
    }

    public function testEndorsementAutheticationHasAccessDenied(): void
    {
        $result = $this->verifyEndorsementAuth($this->endorsementEmailToken, $this->firstEndorsement, 'access_denied');
        $this->assertEquals(PlauditPreEndorsementHandler::AUTH_ACCESS_DENIED, $result);
    }

    private function createBindingFixture(): array
    {
        $submission = new Submission();
        $submission->setId(10);
        $submission->setData('contextId', 1);

        $endorsementPublication = new Publication();
        $endorsementPublication->setId(20);
        $endorsementPublication->setData('submissionId', 10);

        $newerPublication = new Publication();
        $newerPublication->setId(21);
        $newerPublication->setData('submissionId', 10);

        $endorsement = Repo::endorsement()->newDataObject(['contextId' => 1, 'publicationId' => 20]);

        return [$endorsement, $endorsementPublication, $newerPublication, $submission];
    }

    public function testEndorsementOfRequestedPublicationIsAccepted(): void
    {
        [$endorsement, $endorsementPublication, $newerPublication, $submission] = $this->createBindingFixture();

        $this->assertTrue(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $endorsementPublication, $endorsementPublication, $submission, 1));
        $this->assertTrue(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $newerPublication, $endorsementPublication, $submission, 1));
    }

    public function testPublicationOfAnotherSubmissionIsRejected(): void
    {
        [$endorsement, $endorsementPublication, , $submission] = $this->createBindingFixture();
        $otherPublication = new Publication();
        $otherPublication->setId(30);
        $otherPublication->setData('submissionId', 11);
        $otherSubmission = new Submission();
        $otherSubmission->setId(11);
        $otherSubmission->setData('contextId', 1);

        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $otherPublication, $endorsementPublication, $otherSubmission, 1));
        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $otherPublication, $endorsementPublication, $submission, 1));
    }

    public function testEndorsementOrSubmissionOfAnotherContextIsRejected(): void
    {
        [$endorsement, $endorsementPublication, , $submission] = $this->createBindingFixture();

        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $endorsementPublication, $endorsementPublication, $submission, 2));

        $submission->setData('contextId', 2);
        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $endorsementPublication, $endorsementPublication, $submission, 1));
    }

    public function testMissingEndorsementDataIsRejected(): void
    {
        [$endorsement, $endorsementPublication, , $submission] = $this->createBindingFixture();

        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication(null, $endorsementPublication, $endorsementPublication, $submission, 1));
        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, null, $endorsementPublication, $submission, 1));
        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $endorsementPublication, null, $submission, 1));
        $this->assertFalse(PlauditPreEndorsementHandler::endorsementMatchesPublication($endorsement, $endorsementPublication, $endorsementPublication, null, 1));
    }
}
