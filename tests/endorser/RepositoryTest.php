<?php

namespace APP\plugins\generic\plauditPreEndorsement\tests\endorser;

use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Endorser;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Repository;
use PKP\tests\DatabaseTestCase;
use APP\plugins\generic\plauditPreEndorsement\tests\helpers\TestHelperTrait;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;

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
            'endorsers'
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextId = $this->createServerMock();
        $this->publicationId = $this->createPublicationMock();
        $this->params = [
            'contextId' => $this->contextId,
            'name' => 'DummyEndorser',
            'email' => "DummyEndorser@mailinator.com.br",
            'publicationId' => $this->publicationId,
            'status' => null,
            'orcid' => null,
            'emailToken' => null,
            'emailCount' => 0
        ];
        $this->addSchemaFile('endorser');
    }

    public function testGetNewEndorserObject(): void
    {
        $repository = app(Repository::class);
        $endorser = $repository->newDataObject();
        self::assertInstanceOf(Endorser::class, $endorser);
        $endorser = $repository->newDataObject($this->params);
        self::assertEquals($this->params, $endorser->_data);
    }

    public function testCrud(): void
    {
        $repository = app(Repository::class);
        $endorser = $repository->newDataObject($this->params);
        $insertedEndorserId = $repository->add($endorser);
        $this->params['id'] = $insertedEndorserId;

        $fetchedEndorser = $repository->get($insertedEndorserId, $this->contextId);
        self::assertEquals($this->params, $fetchedEndorser->_data);

        $this->params['emailToken'] = 'iuqwidub78a9qbkjabiao';
        $this->params['emailCount'] += 1;
        $repository->edit($endorser, $this->params);

        $fetchedEndorser = $repository->get($endorser->getId(), $this->contextId);
        self::assertEquals($this->params, $fetchedEndorser->_data);

        $repository->delete($endorser);
        self::assertFalse($repository->exists($endorser->getId()));
    }

    public function testCollectorFilterByContextAndPublicationId(): void
    {
        $repository = app(Repository::class);
        $endorser = $repository->newDataObject($this->params);

        $repository->add($endorser);

        $endorsers = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByPublicationIds([$this->publicationId])
            ->getMany();
        self::assertTrue(in_array($endorser, $endorsers->all()));
    }

    public function testEmptyCollectorFilterByContextAndPublicationId(): void
    {
        $repository = app(Repository::class);
        $endorser = $repository->newDataObject($this->params);
        $newMockPublicationId = 2;
        $newPublicationId = $this->createPublicationMock($newMockPublicationId);
        $endorser->setPublicationId($newPublicationId);

        $repository->add($endorser);

        $endorsers = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByPublicationIds([$this->publicationId])
            ->getMany();
        self::assertFalse(in_array($endorser, $endorsers->all()));
    }

    public function testCollectorFilterByContextAndStatus(): void
    {
        $repository = app(Repository::class);
        $this->params['status'] = Endorsement::STATUS_CONFIRMED;
        $endorser = $repository->newDataObject($this->params);

        $repository->add($endorser);

        $endorsers = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByStatus([Endorsement::STATUS_CONFIRMED])
            ->getMany();
        self::assertTrue(in_array($endorser, $endorsers->all()));
    }

    public function testEmptyCollectorFilterByContextAndStatus(): void
    {
        $repository = app(Repository::class);
        $this->params['status'] = Endorsement::STATUS_CONFIRMED;
        $endorser = $repository->newDataObject($this->params);

        $repository->add($endorser);

        $endorsers = $repository->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByStatus([Endorsement::STATUS_NOT_CONFIRMED])
            ->getMany();
        self::assertFalse(in_array($endorser, $endorsers->all()));
    }
}
