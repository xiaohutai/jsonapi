<?php


namespace Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;
use Bolt\Storage\Query\ContentQueryParser;
use Bolt\Storage\Query\SearchQuery;
use Doctrine\DBAL\Query\QueryBuilder;

class PagingHandler
{

    public function __invoke(ContentQueryParser $contentQuery)
    {
        $set = new PagingResultSet();

        foreach ($contentQuery->getContentTypes() as $contenttype) {
            if ($searchParam = $contentQuery->getParameter('filter')) {
                $query = $contentQuery->getService('search');
            } else {
                $query = $contentQuery->getService('select');
            }

            $repo = $contentQuery->getEntityManager()->getRepository($contenttype);
            $query->setQueryBuilder($repo->createQueryBuilder($contenttype));
            $query->setContentType($contenttype);
            $query->setParameters($contentQuery->getParameters());

            if ($query instanceof SearchQuery) {
                $query->setSearch($searchParam);
            }

            $contentQuery->runDirectives($query);

            $query = $repo->getQueryBuilderAfterMappings($query);

            /** @var QueryBuilder $query2 */
            $query2 = clone $query->getQueryBuilder();
            $qb = clone $query->getQueryBuilder();

            $query2
                ->resetQueryParts(['groupBy', 'maxResults', 'firstResult'])
                ->select("COUNT(*) as total");

            $totalItems = $repo->findResult($query2);

            $result = $repo->findResults($qb);
            if ($result) {
                $set->add($result, $contenttype);
                $set->setTotalResults($totalItems);

                /** @var Page $page */
                $page = $contentQuery->getDirective('paginate');
                $set->setTotalPages($totalItems, $page->getSize());
            }

            return $set;
        }
    }
}