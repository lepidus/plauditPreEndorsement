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
        $this->addSchemaFile('endorser');
    }

    public function testNewDataObjectIsInstanceOfEndorser(): void
    {
        $endorser = $this->endorserDAO->newDataObject();
        self::assertInstanceOf(Endorser::class, $endorser);
    }
}
