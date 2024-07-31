<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests\endorsement;

use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Repository;
use PKP\tests\DatabaseTestCase;
use APP\plugins\generic\plauditPreEndorsement\tests\helpers\TestHelperTrait;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;

class RepositoryTest extends DatabaseTestCase
{
    use TestHelperTrait;

    private $contextId;
    private $publicationId;
    private array $params;

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
        $this->contextId = $this->createServerMock();
        $this->publicationId = $this->createPublicationMock();
        $this->params = [
            'contextId' => $this->contextId,
            'name' => 'DummyEndorsement',
            'email' => "DummyEndorsement@mailinator.com.br",
            'publicationId' => $this->publicationId,
            'status' => null,
            'orcid' => null,
            'emailToken' => null,
            'emailCount' => 0
        ];
        $this->addSchemaFile('endorsement');
    }

    public function testGetNewEndorsementObject(): void
    {
        $repository = app(Repository::class);
        $endorsement = $repository->newDataObject();
        self::assertInstanceOf(Endorsement::class, $endorsement);
        $endorsement = $repository->newDataObject($this->params);
        self::assertEquals($this->params, $endorsement->_data);
    }

    public function testCrud(): void
    {
        $repository = app(Repository::class);
        $endorsement = $repository->newDataObject($this->params);
        $insertedEndorsementId = $repository->add($endorsement);
        $this->params['id'] = $insertedEndorsementId;

        $fetchedEndorsement = $repository->get($insertedEndorsementId, $this->contextId);
        self::assertEquals($this->params, $fetchedEndorsement->_data);

        $this->params['emailToken'] = 'iuqwidub78a9qbkjabiao';
        $this->params['emailCount'] += 1;
        $repository->edit($endorsement, $this->params);

        $fetchedEndorsement = $repository->get($endorsement->getId(), $this->contextId);
        self::assertEquals($this->params, $fetchedEndorsement->_data);

        $repository->delete($endorsement);
        self::assertFalse($repository->exists($endorsement->getId()));
    }

    public function testCollectorFilterByContextAndPublicationId(): void
    {
        $repository = app(Repository::class);
        $endorsement = $repository->newDataObject($this->params);

        $repository->add($endorsement);

        $endorsements = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByPublicationIds([$this->publicationId])
            ->getMany();
        self::assertTrue(in_array($endorsement, $endorsements->all()));
    }

    public function testEmptyCollectorFilterByContextAndPublicationId(): void
    {
        $repository = app(Repository::class);
        $endorsement = $repository->newDataObject($this->params);
        $newMockPublicationId = 2;
        $newPublicationId = $this->createPublicationMock($newMockPublicationId);
        $endorsement->setPublicationId($newPublicationId);

        $repository->add($endorsement);

        $endorsements = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByPublicationIds([$this->publicationId])
            ->getMany();
        self::assertFalse(in_array($endorsement, $endorsements->all()));
    }

    public function testCollectorFilterByContextAndStatus(): void
    {
        $repository = app(Repository::class);
        $this->params['status'] = Endorsement::STATUS_CONFIRMED;
        $endorsement = $repository->newDataObject($this->params);

        $repository->add($endorsement);

        $endorsements = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByStatus([Endorsement::STATUS_CONFIRMED])
            ->getMany();
        self::assertTrue(in_array($endorsement, $endorsements->all()));
    }

    public function testEmptyCollectorFilterByContextAndStatus(): void
    {
        $repository = app(Repository::class);
        $this->params['status'] = Endorsement::STATUS_CONFIRMED;
        $endorsement = $repository->newDataObject($this->params);

        $repository->add($endorsement);

        $endorsements = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByStatus([Endorsement::STATUS_NOT_CONFIRMED])
            ->getMany();
        self::assertFalse(in_array($endorsement, $endorsements->all()));
    }

    public function testGetEndorsementByEmail(): void
    {
        $repository = app(Repository::class);
        $endorsement = $repository->newDataObject($this->params);
        $repository->add($endorsement);

        $fetchedEndorsement = $repository->getByEmail($this->params['email'], $endorsement->getPublicationId(), $endorsement->getContextId());
        self::assertEquals($endorsement, $fetchedEndorsement);
    }
}
