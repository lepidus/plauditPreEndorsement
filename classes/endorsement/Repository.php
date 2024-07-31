<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\endorsement;

use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\DAO;

class Repository
{
    public $dao;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function newDataObject(array $params = []): Endorsement
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    public function get(int $id, int $contextId = null): ?Endorsement
    {
        return $this->dao->get($id, $contextId);
    }

    public function add(Endorsement $endorsement): int
    {
        $id = $this->dao->insert($endorsement);
        return $id;
    }

    public function edit(Endorsement $endorsement, array $params)
    {
        $newEndorsement = clone $endorsement;
        $newEndorsement->setAllData(array_merge($newEndorsement->_data, $params));

        $this->dao->update($newEndorsement);
    }

    public function delete(Endorsement $endorsement)
    {
        $this->dao->delete($endorsement);
    }

    public function exists(int $id, int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    public function getByEmail(string $email, int $publicationId, int $contextId): ?Endorsement
    {
        return $this->dao->getByEmail($email, $publicationId, $contextId);
    }

    public function getCollector(): Collector
    {
        return app(Collector::class);
    }
}
