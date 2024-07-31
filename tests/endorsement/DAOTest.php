<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests\endorsement;

use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\DAO;
use PKP\tests\DatabaseTestCase;
use APP\plugins\generic\plauditPreEndorsement\tests\helpers\TestHelperTrait;

class DAOTest extends DatabaseTestCase
{
    use TestHelperTrait;

    private $contextId;
    private $endorsementDAO;
    private $publicationId;

    protected function getAffectedTables(): array
    {
        return [
            ...parent::getAffectedTables(),
            'endorsements'
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->endorsementDAO = app(DAO::class);
        $this->contextId = $this->createServerMock();
        $this->publicationId = $this->createPublicationMock();
        $this->addSchemaFile('endorsement');
    }

    public function testNewDataObjectIsInstanceOfEndorsement(): void
    {
        $endorsement = $this->endorsementDAO->newDataObject();
        self::assertInstanceOf(Endorsement::class, $endorsement);
    }

    public function testCreateEndorsement(): void
    {
        $fetchedEndorsement = $this->retrieveEndorsement();

        self::assertEquals([
            'id' => $fetchedEndorsement->getId(),
            'contextId' => $this->contextId,
            'name' => 'DummyEndorsement',
            'email' => "DummyEndorsement@mailinator.com.br",
            'publicationId' => $this->publicationId,
            'status' => null,
            'orcid' => null,
            'emailToken' => null,
            'emailCount' => 0
        ], $fetchedEndorsement->_data);
    }

    public function testDeleteEndorsement(): void
    {
        $fetchedEndorsement = $this->retrieveEndorsement();

        $this->endorsementDAO->delete($fetchedEndorsement);
        self::assertFalse($this->endorsementDAO->exists($fetchedEndorsement->getId(), $this->contextId));
    }

    public function testEditEndorsement(): void
    {
        $fetchedEndorsement = $this->retrieveEndorsement();

        $updatedName = "Updated name";
        $fetchedEndorsement->setName($updatedName);

        $this->endorsementDAO->update($fetchedEndorsement);

        $fetchedEndorsement = $this->retrieveEndorsement($fetchedEndorsement->getId());

        self::assertEquals($fetchedEndorsement->getName(), $updatedName);
    }

    public function testGetEndorsementByEmail(): void
    {
        $endorsement = $this->retrieveEndorsement();

        self::assertEquals(
            $this->endorsementDAO->getByEmail(
                $endorsement->getEmail(),
                (int) $endorsement->getPublicationId(),
                (int) $endorsement->getContextId()
            ),
            $endorsement
        );
    }

    private function retrieveEndorsement($endorsementId = null)
    {
        $insertedEndorsementId = isset($endorsementId) ? $endorsementId : $this->createEndorsement();

        return $this->endorsementDAO->get(
            $insertedEndorsementId,
            $this->contextId
        );
    }

    private function createEndorsement()
    {
        $endorsementDataObject = $this->createEndorsementDataObject($this->contextId, $this->publicationId);
        return $this->endorsementDAO->insert($endorsementDataObject);
    }
}
