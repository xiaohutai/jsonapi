<?php


namespace Bolt\Extension\Bolt\JsonApi\Storage\Query;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Storage\Query\QueryResultset;

class PagingResultSet extends QueryResultset
{

    protected $totalResults;

    protected $totalPages;

    /**
     * @return mixed
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
     * @return mixed
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
        $this->totalResults = $totalResults;
        return $this;
    }
}
