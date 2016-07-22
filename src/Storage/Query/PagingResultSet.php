<?php


namespace Bolt\Extension\Bolt\JsonApi\Storage\Query;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Storage\Query\QueryResultset;

/**
 * Class PagingResultSet
 * Extends the QueryResultSet to add new methods to handle pagination
 * @package Bolt\Extension\Bolt\JsonApi\Storage\Query
 */
class PagingResultSet extends QueryResultset
{

    /** @var int $totalResults */
    protected $totalResults = 0;

    /** @var int $totalPages */
    protected $totalPages;

    /**
     * @return int
     */
    public function getTotalPages()
    {
        return $this->totalPages;
    }

    /**
     * @param $totalItems
     * @param $size
     * @return $this
     */
    public function setTotalPages($totalItems, $size)
    {
        $totalPages = $totalItems/$size;

        if ($totalPages > 1) {
            //Round the total pages up (Don't want 2.5)
            $this->totalPages = ceil($totalPages);
            return $this;
        }

        $this->totalPages =  Page::DEFAULT_PAGE_NUMBER;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalResults()
    {
        return (int) $this->totalResults;
    }

    /**
     * @param mixed $totalResults
     * @return PagingResultSet
     */
    public function setTotalResults($totalResults)
    {
        $this->totalResults += $totalResults;
        return $this;
    }
}
