<?php


namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Helpers\DataLinks;
use Bolt\Extension\Bolt\JsonApi\Helpers\Paginator;
use Bolt\Extension\Bolt\JsonApi\Parser\Parser;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Storage\Query\Query;
use Bolt\Storage\Query\QueryResultset;

class SearchAction
{
    protected $query;

    protected $parameters;

    protected $parser;

    protected $dataLinks;

    protected $config;
    
    public function __construct(
        Query $query,
        Parser $parser,
        DataLinks $dataLinks,
        Config $config
    ) {
        $this->query = $query;
        $this->parser = $parser;
        $this->dataLinks = $dataLinks;
        $this->config = $config;
    }

    public function handle($contentType, ParameterCollection $parameters)
    {
        $this->parameters = $parameters;

        // If no $contenttype is set, search all 'searchable' contenttypes.
        $baselink = "$contentType/search";
        if ($contentType === null) {
            $allcontenttypes = array_keys($this->config->getContentTypes());
            // This also fetches unallowed ones:
            // $allcontenttypes = array_keys($this->app['config']->get('contenttypes'));
            $allcontenttypes = implode(',', $allcontenttypes);
            $contentType = "($allcontenttypes)";
            $baselink = 'search';
        }

        if (! $q = $request->get('q')) {
            throw new ApiInvalidRequestException(
                "No query parameter q specified."
            );
        }

        $queryParameters = array_merge($parameters->getQueryParameters(), ['filter' => $q]);

        /** @var QueryResultset $results */
        $results = $app['query']
            ->getContent($baselink, $queryParameters)
            ->get($contentType);

        $page = $parameters->getParametersByType('page');

        $totalItems = count($results);

        $totalPages = ceil(($totalItems/$page['limit']) > 1 ? ($totalItems/$page['limit']) : 1);

        $offset = ($page['number']-1)*$page['limit'];

        $results = array_splice($results, $offset, $page['limit']);

        if (! $results || count($results) === 0) {
            throw new ApiNotFoundException(
                "No search results found for query [$q]"
            );
        }

        foreach ($results as $key => $item) {
            $ct = $item->getSlug();
            // optimize this part...
            $fields = $parameters->get('fields')->getFields();
            $items[$key] = $this->APIHelper->cleanItem($item, $fields);
        }

        return new ApiResponse([
            'links' => $this->APIHelper->makeLinks(
                $baselink,
                $page['number'],
                $totalPages,
                $page['limit']
            ),
            'meta' => [
                "count" => count($items),
                "total" => $totalItems
            ],
            'data' => $items,
        ], $this->config);
    }

    protected function fetchIncludes($includes, $results)
    {
        foreach ($includes as $include) {
            //Loop through all results
            foreach ($results as $key => $item) {
                //Loop through all relationships
                foreach ($item->relation[$include] as $related) {
                    $fields = $this->parameters->get('includes')->getFieldsByContentType($include);
                    $included[$key] = $this->parser->parseItem($related, $fields);
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

