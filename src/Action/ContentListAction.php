<?php


namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Storage\Query\Query;
use Bolt\Storage\Query\QueryResultset;

class ContentListAction
{
    protected $query;

    protected $parameters;

    public function __construct(
        Query $query,
        ParameterCollection $parameters,
        APIHelper $APIHelper
    ) {
    }

    public function handle()
    {
        /** @var QueryResultset $results */
        $results = $this->query
            ->getContent($contentType, $this->parameters->getQueryParameters())
            ->get($contentType);

        $totalItems = count($results);

        $includes = $this->parameters->getParametersByType('includes');

        $page = $this->parameters->getParametersByType('page');

        $offset = ($page['number']-1)*$page['limit'];

        $results = array_splice($results, $offset, $page['limit']);

        $items = [];

        foreach ($results as $key => $item) {
            $fields = $this->parameters->get('fields')->getFields();
            $items[$key] = $this->APIHelper->cleanItem($item, $fields);
        }

        $totalPages = ceil(($totalItems/$page['limit']) > 1 ? ($totalItems/$page['limit']) : 1);

        $response = [
            'links' => $this->APIHelper->makeLinks(
                $contentType,
                $page['number'],
                $totalPages,
                $page['limit']
            ),
            'meta' => [
                "count" => count($items),
                "total" => $totalItems
            ],
            'data' => $items,
        ];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        return new ApiResponse($response, $this->config);
    }

    protected function fetchIncludes()
    {
        foreach ($includes as $include) {
            //Loop through all results
            foreach ($results as $key => $item) {
                //Loop through all relationships
                foreach ($item->relation[$include] as $related) {
                    $fields = $parameters->get('includes')->getFieldsByContentType($include);
                    $included[$key] = $this->APIHelper->cleanItem($related, $fields);
                }
            }
        }
    }

    protected function checkResults(QueryResultset $results)
    {
        if (! $results || count($results) === 0) {
            throw new ApiInvalidRequestException(
                "Bad request: There were no results based upon your criteria!"
            );
        }
    }
}

