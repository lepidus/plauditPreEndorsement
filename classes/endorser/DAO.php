<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\endorser;

use PKP\core\EntityDAO;
use Illuminate\Support\LazyCollection;
use PKP\core\traits\EntityWithParent;

class DAO extends EntityDAO
{
    use EntityWithParent;

    public $schema = 'endorser';
    public $table = 'endorsers';
    public $primaryKeyColumn = 'endorser_id';
    public $primaryTableColumns = [
        'id' => 'endorser_id',
        'contextId' => 'context_id',
        'publicationId' => 'publication_id'
    ];

    public function getParentColumn(): string
    {
        return 'context_id';
    }

    public function newDataObject(): Endorser
    {
        return app(Endorser::class);
    }

    public function insert(Endorser $endorser): int
    {
        return parent::_insert($endorser);
    }

    public function delete(Endorser $endorser)
    {
        return parent::_delete($endorser);
    }

    public function update(Endorser $endorser)
    {
        return parent::_update($endorser);
    }

    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->count();
    }

    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->endorser_id => $this->fromRow($row);
            }
        });
    }

    public function fromRow(object $row): Endorser
    {
        return parent::fromRow($row);
    }
}
