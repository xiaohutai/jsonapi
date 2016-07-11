<?php


namespace Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler\Directive;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Storage\Query\QueryInterface;

/**
 * Class PagerHandler
 * @package Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler\Directive
 */
class PagerHandler
{
    /**
     * @param QueryInterface $query
     * @param Page           $page
     */
    public function __invoke(QueryInterface $query, Page $page)
    {
        //Get offset
        $offset = ($page->getNumber()-1) * $page->getSize();

        //Set pagination offsets and limits
        $query->getQueryBuilder()->setFirstResult($offset);
        $query->getQueryBuilder()->setMaxResults($page->getSize());
    }
}
