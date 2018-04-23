<?php

namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SearchAction
 *
 * @package Bolt\Extension\Bolt\JsonApi\Action
 */
class SearchAction extends FetchAction
{
    /**
     * @param null                $contentType
     * @param Request             $request
     * @param ParameterCollection $parameters
     *
     * @return ApiResponse
     */
    public function handle($contentType = null, Request $request, ParameterCollection $parameters)
    {
        $search = $parameters->get('search')->getSearch();

        if (! $search) {
            throw new ApiInvalidRequestException(
                'No query parameter q specified.'
            );
        }

        // If no $contenttype is set, search all 'searchable' contenttypes.
        $baselink = "$contentType/pager";
        $searchContentType = $baselink;
        if ($contentType === null) {
            $allcontenttypes = array_keys($this->config->getContentTypes());
            // This also fetches unallowed ones:
            // $allcontenttypes = array_keys($this->app['config']->get('contenttypes'));
            $allcontenttypes = implode(',', $allcontenttypes);
            $baselink = "($allcontenttypes)/pager";
            $searchContentType = 'search';
        }

        $queryParameters = array_merge($parameters->getQueryParameters(), $parameters->getParametersByType('search'));

        /** @var PagingResultSet $results */
        $set = $this->query
            ->getContent($baselink, $queryParameters);

        $results = $set->get($contentType);

        /** @var Page $page */
        $page = $parameters->get('page');

        foreach ($results as $key => $item) {
            $contentType = (string) $item->getContenttype();
            // optimize this part...
            $fields = $parameters->get('fields')->getFields($contentType);
            $items[$key] = $this->parser->parseItem($item, $fields);
        }

        return new ApiResponse([
            'links' => $this->dataLinks->makeLinks(
                $searchContentType,
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
        ], $this->config);
    }
}
