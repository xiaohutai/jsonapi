<?php


namespace Bolt\Extension\Bolt\JsonApi\Storage;

use Bolt\Storage\Query\QueryInterface;
use Bolt\Storage\Repository\ContentRepository;
use Doctrine\DBAL\Query\QueryBuilder;

class Repository extends ContentRepository
{

    /**
     * @param QueryInterface $query
     * @return QueryInterface
     * This method is used to load all of the default query builder information and return
     *  a query builder back to manipulate the query. This allows for easier pagination and
     *  manipulating queries before fetching.
     */
    public function getQueryBuilderAfterMappings(QueryInterface $query)
    {
        $this->query($query);

        $queryBuilder = $query->build();

        $this->load($queryBuilder);

        return $query;
    }

    /**
     * @param QueryBuilder $query
     * @return bool|mixed
     * Since the queries are built already, we don't need to run all of the other mappings
     * before. Now we can just fetch the results of the query instead of running everything else.
     */
    public function findResults(QueryBuilder $query)
    {
        $result = $query->execute()->fetchAll();
        if ($result) {
            return $this->hydrateAll($result, $query);
        } else {
            return false;
        }
    }

    /**
     * @param QueryBuilder $query
     * @return bool|mixed
     * Since the queries are built already, we don't need to run all of the other mappings
     * before. Now we can just fetch the results of the query instead of running everything else.
     * This fetches a single result (count) and returns the total.
     */
    public function findResult(QueryBuilder $query)
    {
        $result = $query->execute()->fetch();
        if ($result) {
            return $result['total'];
        } else {
            return false;
        }
    }
}
