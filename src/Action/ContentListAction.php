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
use Bolt\Extension\Bolt\JsonApi\Storage\Query\PagingResultSet;
use Bolt\Storage\Query\Query;
use Bolt\Storage\Query\QueryResultset;

class ContentListAction
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

        $page = $this->parameters->get('page');

        $queryParameters = array_merge($this->parameters->getQueryParameters(), ['paginate' => $page]);

        /** @var PagingResultSet $set */
        $set = $this->query
            ->getContent("$contentType/pager", $queryParameters);

        $results = $set->get($contentType);

        if (! $results || count($results) === 0) {
            throw new ApiInvalidRequestException(
                "Bad request: There were no results based upon your criteria!"
            );
        }

        $includes = $this->parameters->getParametersByType('includes');

        $this->fetchIncludes($includes, $results);
        
        $items = [];

        foreach ($results as $key => $item) {
            $fields = $this->parameters->get('fields')->getFields();
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
}

