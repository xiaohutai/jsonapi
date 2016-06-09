<?php


namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;

class SearchAction extends FetchAction
{
    public function handle($contentType, ParameterCollection $parameters)
    {
        // If no $contenttype is set, search all 'searchable' contenttypes.
        $baselink = "$contentType/pager";
        if ($contentType === null) {
            $allcontenttypes = array_keys($this->config->getContentTypes());
            // This also fetches unallowed ones:
            // $allcontenttypes = array_keys($this->app['config']->get('contenttypes'));
            $allcontenttypes = implode(',', $allcontenttypes);
            $contentType = "($allcontenttypes)";
            $baselink = 'pager';
        }

        $search = $parameters->get('search')->getSearch();

        if (! $search) {
            throw new ApiInvalidRequestException(
                "No query parameter q specified."
            );
        }

        $queryParameters = array_merge($parameters->getQueryParameters(), $parameters->getParametersByType('search'));

        /** @var PagingResultSet $results */
        $set = $this->query
            ->getContent($baselink, $queryParameters);

        $results = $set->get($contentType);

        $page = $parameters->getParametersByType('page');

        $this->throwErrorOnNoResults($results, "No search results found for query [$search]");

        foreach ($results as $key => $item) {
            // optimize this part...
            $fields = $parameters->get('fields')->getFields();
            $items[$key] = $this->parser->parseItem($item, $fields);
        }

        return new ApiResponse([
            'links' => $this->dataLinks->makeLinks(
                $baselink,
                $page['number'],
                $set->getTotalPages(),
                $page['limit']
            ),
            'meta' => [
                "count" => count($items),
                "total" => $set->getTotalResults()
            ],
            'data' => $items,
        ], $this->config);
    }
}
