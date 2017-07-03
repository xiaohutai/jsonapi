<?php

namespace Bolt\Extension\Bolt\JsonApi\Storage;

use Bolt\Storage\Query\QueryInterface;
use Bolt\Storage\Repository\ContentRepository;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class Repository
 *
 * @package Bolt\Extension\Bolt\JsonApi\Storage
 */
class Repository extends ContentRepository
{
    /**
     * This method is used to load all of the default query builder information and return
     *  a query builder back to manipulate the query. This allows for easier pagination and
     *  manipulating queries before fetching.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface
     */
    public function getQueryBuilderAfterMappings(QueryInterface $query)
    {
        $this->query($query);

        $queryBuilder = $query->build();

        $this->load($queryBuilder);

        return $query;
    }

}
