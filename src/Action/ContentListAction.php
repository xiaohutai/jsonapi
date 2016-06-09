<?php


namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;

class ContentListAction extends FetchAction
{
    public function handle($contentType, ParameterCollection $parameters)
    {
        $page = $parameters->get('page');

        $queryParameters = array_merge($parameters->getQueryParameters(), ['paginate' => $page]);

        /** @var PagingResultSet $set */
        $set = $this->query
            ->getContent("$contentType/pager", $queryParameters);

        $results = $set->get($contentType);

        $this->throwErrorOnNoResults($results, "Bad request: There were no results based upon your criteria!");

        $this->fetchIncludes(
            $parameters->getParametersByType('includes'),
            $results,
            $parameters
        );

        $page = $parameters->getParametersByType('page');

        $items = [];

        foreach ($results as $key => $item) {
            $fields = $parameters->get('fields')->getFields();
            $items[$key] = $this->parser->parseItem($item, $fields);
        }

        $response = [
            'links' => $this->dataLinks->makeLinks(
                $contentType,
                $page['number'],
                $set->getTotalPages(),
                $page['limit']
            ),
            'meta' => [
                "count" => count($items),
                "total" => $set->getTotalResults()
            ],
            'data' => $items,
        ];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        return new ApiResponse($response, $this->config);
    }
}
