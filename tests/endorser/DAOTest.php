<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests\endorser;

use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Endorser;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\DAO;
use PKP\tests\DatabaseTestCase;
use APP\plugins\generic\plauditPreEndorsement\tests\helpers\TestHelperTrait;

class DAOTest extends DatabaseTestCase
{
    use TestHelperTrait;

    private $contextId;
    private $endorserDAO;
    private $publicationId;

    protected function getAffectedTables(): array
    {
        return [
            ...parent::getAffectedTables(),
            'endorsers'
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->endorserDAO = app(DAO::class);
        $this->contextId = $this->createServerMock();
        $this->publicationId = $this->createPublicationMock();
        $this->addSchemaFile('endorser');
    }

    public function testNewDataObjectIsInstanceOfEndorser(): void
    {
        $endorser = $this->endorserDAO->newDataObject();
        self::assertInstanceOf(Endorser::class, $endorser);
    }

    public function testCreateEndorser(): void
    {
        $fetchedEndorser = $this->retrieveEndorser();

        self::assertEquals([
            'id' => $fetchedEndorser->getId(),
            'contextId' => $this->contextId,
            'name' => 'DummyEndorser',
            'email' => "DummyEndorser@mailinator.com.br",
            'publicationId' => $this->publicationId,
            'status' => null,
            'orcid' => null,
            'emailToken' => null,
            'emailCount' => 0
        ], $fetchedEndorser->_data);
    }

    public function testDeleteEndorser(): void
    {
        $fetchedEndorser = $this->retrieveEndorser();

        $this->endorserDAO->delete($fetchedEndorser);
        self::assertFalse($this->endorserDAO->exists($fetchedEndorser->getId(), $this->contextId));
    }

    public function testEditEdorser(): void
    {
        $fetchedEndorser = $this->retrieveEndorser();

        $updatedName = "Updated name";
        $fetchedEndorser->setName($updatedName);

        $this->endorserDAO->update($fetchedEndorser);

        $fetchedEndorser = $this->retrieveEndorser($fetchedEndorser->getId());

        self::assertEquals($fetchedEndorser->getName(), $updatedName);
    }

    private function retrieveEndorser($endorserId = null)
    {
        $insertedEndorserId = isset($endorserId) ? $endorserId : $this->createEndorser();

        return $this->endorserDAO->get(
            $insertedEndorserId,
            $this->contextId
        );
    }

    private function createEndorser()
    {
        $endorserDataObject = $this->createEndorserDataObject($this->contextId, $this->publicationId);
        return $this->endorserDAO->insert($endorserDataObject);
    }
}
