<?php

namespace Bolt\Extension\Bolt\JsonApi\Helpers;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;

class Paginator
{
    const DEFAULT_PAGE = 1;

    /** @var array $results */
    protected $results;

    /** @var Page $page */
    protected $page;

    public function __construct(array $results, Page $page)
    {
        $this->results = $results;
        $this->page = $page;
    }

    public function getResultsPaginated()
    {
        return array_splice(
            $this->results,
            $this->calculateOffset(),
            $this->page->getSize()
        );
    }

    public function getTotalPages()
    {
        $totalPages = $this->getTotalItems()/$this->page->getSize();

        if ($totalPages > 1) {
            //Round the total pages up (Don't want 2.5)
            return ceil($totalPages);
        }

        return self::DEFAULT_PAGE;
    }

    public function getTotalItems()
    {
        return count($this->results);
    }

    protected function calculateOffset()
    {
        return ($this->page->getNumber() - 1) * $this->page->getSize();
    }
}
