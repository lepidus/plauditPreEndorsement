<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\endorsement;

use PKP\core\EntityDAO;
use Illuminate\Support\LazyCollection;
use PKP\core\traits\EntityWithParent;

class DAO extends EntityDAO
{
    use EntityWithParent;

    public $schema = 'endorsement';
    public $table = 'endorsements';
    public $primaryKeyColumn = 'endorsement_id';
    public $primaryTableColumns = [
        'id' => 'endorsement_id',
        'contextId' => 'context_id',
        'publicationId' => 'publication_id',
        'name' => 'name',
        'email' => 'email',
        'status' => 'status',
        'orcid' => 'orcid',
        'emailToken' => 'email_token',
        'emailCount' => 'email_count'
    ];

    public function getParentColumn(): string
    {
        return 'context_id';
    }

    public function newDataObject(): Endorsement
    {
        return app(Endorsement::class);
    }

    public function insert(Endorsement $endorsement): int
    {
        return parent::_insert($endorsement);
    }

    public function delete(Endorsement $endorsement)
    {
        return parent::_delete($endorsement);
    }

    public function update(Endorsement $endorsement)
    {
        return parent::_update($endorsement);
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
                yield $row->endorsement_id => $this->fromRow($row);
            }
        });
    }

    public function fromRow(object $row): Endorsement
    {
        return parent::fromRow($row);
    }
}
