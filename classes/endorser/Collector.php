<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\endorser;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use Illuminate\Support\LazyCollection;

class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $contextIds = null;
    public ?array $publicationIds = null;
    public ?array $status = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function filterByContextIds(?array $contextIds): Collector
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    public function filterByPublicationIds(?array $publicationIds): Collector
    {
        $this->publicationIds = $publicationIds;
        return $this;
    }

    public function filterByStatus(?array $status): Collector
    {
        $this->status = $status;
        return $this;
    }

    public function getQueryBuilder(): Builder
    {
        $queryBuilder = DB::table($this->dao->table . ' as endorsers')
            ->select(['endorsers.*']);

        if (isset($this->contextIds)) {
            $queryBuilder->whereIn('endorsers.context_id', $this->contextIds);
        }

        if (isset($this->publicationIds)) {
            $queryBuilder->whereIn('endorsers.publication_id', $this->publicationIds);
        }

        if (isset($this->status)) {
            $queryBuilder->whereIn('endorsers.status', $this->status);
        }

        return $queryBuilder;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }
}
