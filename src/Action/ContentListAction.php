<?php

namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ContentListAction
 *
 * @package Bolt\Extension\Bolt\JsonApi\Action
 */
class ContentListAction extends FetchAction
{
    /**
     * @param $contentType
     * @param Request             $request
     * @param ParameterCollection $parameters
     *
     * @return ApiResponse
     */
    public function handle($contentType, Request $request, ParameterCollection $parameters)
    {
        /** @var Page $page */
        $page = $parameters->get('page');

        $queryParameters = array_merge($parameters->getQueryParameters(), $page->getParameter());

        /** @var PagingResultSet $set */
        $set = $this->query
            ->getContent("$contentType/pager", $queryParameters);

        $results = $set->get($contentType);

        $this->throwErrorOnNoResults($results, 'Bad request: There were no results based upon your criteria!');

        $this->fetchIncludes(
            $parameters->getParametersByType('includes'),
            $results,
            $parameters
        );

        $items = [];

        foreach ($results as $key => $item) {
            $fields = $parameters->get('fields')->getFields();
            $items[$key] = $this->parser->parseItem($item, $fields);
        }

        $response = [
            'links' => $this->dataLinks->makeLinks(
                $contentType,
                $page->getNumber(),
                $set->getTotalPages(),
                $page->getSize(),
                $request
            ),
            'meta' => [
                'count' => count($items),
                'total' => $set->getTotalResults(),
            ],
            'data' => $items,
        ];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        return new ApiResponse($response, $this->config);
    }
}
