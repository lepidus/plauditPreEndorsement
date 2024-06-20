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
        $endorserDataObject = $this->createEndorserDataObject($this->contextId, $this->publicationId);
        $insertedEndorserId = $this->endorserDAO->insert($endorserDataObject);

        $fetchedEndorser = $this->endorserDAO->get(
            $insertedEndorserId,
            $this->contextId
        );

        self::assertEquals([
            'id' => $insertedEndorserId,
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
        $endorserDataObject = $this->createEndorserDataObject($this->contextId, $this->publicationId);
        $insertedEndorserId = $this->endorserDAO->insert($endorserDataObject);

        $fetchedEndorser = $this->endorserDAO->get(
            $insertedEndorserId,
            $this->contextId
        );

        $this->endorserDAO->delete($fetchedEndorser);
        self::assertFalse($this->endorserDAO->exists($insertedEndorserId, $this->contextId));
    }

    public function testEditEdorser(): void
    {
        $endorserDataObject = $this->createEndorserDataObject($this->contextId, $this->publicationId);
        $insertedEndorserId = $this->endorserDAO->insert($endorserDataObject);

        $fetchedEndorser = $this->endorserDAO->get(
            $insertedEndorserId,
            $this->contextId
        );

        $updatedName = "Updated name";
        $fetchedEndorser->setName($updatedName);

        $this->endorserDAO->update($fetchedEndorser);

        $fetchedEndorser = $this->endorserDAO->get(
            $insertedEndorserId,
            $this->contextId
        );

        self::assertEquals($fetchedEndorser->getName(), $updatedName);
    }
}
