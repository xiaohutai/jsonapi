<?php

namespace Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;
use Bolt\Storage\Query\ContentQueryParser;
use Bolt\Storage\Query\SearchQuery;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class PagingHandler
 *
 * @package Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler
 */
class PagingHandler
{
    public function __invoke(ContentQueryParser $contentQuery)
    {
        $set = new PagingResultSet();
        $cleanSearchQuery = $contentQuery->getService('search');

        foreach ($contentQuery->getContentTypes() as $contenttype) {
            //Find out if we are searching or just doing a simple query
            if ($contentQuery->hasParameter('filter')) {
                $query = clone $cleanSearchQuery;
            } else {
                $query = $contentQuery->getService('select');
            }

            $repo = $contentQuery->getEntityManager()->getRepository($contenttype);
            $query->setQueryBuilder($repo->createQueryBuilder($contenttype));
            $query->setContentType($contenttype);
            $query->setParameters($contentQuery->getParameters());

            //Set the search parameter if searching
            if ($query instanceof SearchQuery) {
                $query->setSearch($contentQuery->getParameter('filter'));
            }

            //Get Page from the new directive handler that allows pagination
            $paginate = $contentQuery->getDirective('paginate');

            //Set the default limitto the pagination size, since it defaults to null
            $contentQuery->setDirective('limit', $paginate->getSize());

            //Run all of the directives to return the query
            $contentQuery->runDirectives($query);

            //Get the full query so we can manipulate the result with our count query.
            $query = $repo->getQueryBuilderAfterMappings($query);

            //Clone our query builder now to manipulate and find the results.
            /** @var QueryBuilder $query2 */
            $query2 = clone $query->getQueryBuilder();

            /** @var QueryBuilder $qb */
            $qb = clone $query->getQueryBuilder();

            // Get an intersection of current keys, and the ones we need to remove
            $removeparts = array_intersect(array_keys($query2->getQueryParts()), ['maxResults', 'firstResult', 'orderBy']);

            $query2
                ->resetQueryParts($removeparts)
                ->setFirstResult(null)
                ->setMaxResults(null)
                ->select('COUNT(*) as total');

            $totalItems = count($repo->findResults($query2));

            $result = $repo->findResults($qb);
            if ($result) {
                $set->add($result, $contenttype);
                $set->setTotalResults((int) $totalItems);

                /** @var Page $page */
                $page = $contentQuery->getDirective('paginate');
                $set->setTotalPages($totalItems, $page->getSize());
            }
        }

        return $set;
    }
}
