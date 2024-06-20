<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\endorser;

use APP\plugins\generic\plauditPreEndorsement\classes\endorser\DAO;

class Repository
{
    public $dao;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function newDataObject(array $params = []): Endorser
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    public function get(int $id, int $contextId = null): ?Endorser
    {
        return $this->dao->get($id, $contextId);
    }

    public function add(Endorser $endorser): int
    {
        $id = $this->dao->insert($endorser);
        return $id;
    }

    public function edit(Endorser $endorser, array $params)
    {
        $newEndorser = clone $endorser;
        $newEndorser->setAllData(array_merge($newEndorser->_data, $params));

        $this->dao->update($newEndorser);
    }

    public function delete(Endorser $endorser)
    {
        $this->dao->delete($endorser);
    }

    public function exists(int $id, int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    public function getCollector(): Collector
    {
        return app(Collector::class);
    }
}
