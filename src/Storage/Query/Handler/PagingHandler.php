<?php


namespace Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;
use Bolt\Storage\Query\ContentQueryParser;

class PagingHandler
{

    public function __invoke(ContentQueryParser $contentQuery)
    {
        $set = new PagingResultSet();

        foreach ($contentQuery->getContentTypes() as $contenttype) {
            $query = $contentQuery->getService('select');
            $repo = $contentQuery->getEntityManager()->getRepository($contenttype);
            $query->setQueryBuilder($repo->createQueryBuilder($contenttype));
            $query->setContentType($contenttype);
            $query->setParameters($contentQuery->getParameters());

            $contentQuery->runDirectives($query);

            $query2 = clone $query->getQueryBuilder();
            $qb = clone $query->getQueryBuilder();

            $query2
                ->setMaxResults(null)
                ->setFirstResult(null)
                ->select("COUNT(*) as total");

            $query->setQueryBuilder($query2);

            $totalItems = $query->build()->execute()->fetch();
            $totalItems = $totalItems['total'];

            $query->setQueryBuilder($qb);
            $result = $repo->queryWith($query);
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