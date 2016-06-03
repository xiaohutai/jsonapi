<?php
namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Content;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\JSONAPIConverter;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiInvalidRequestResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Storage\Query\QueryResultset;
use Doctrine\Common\Collections\Criteria;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ContentController
 * @package JSONAPI\Controllers
 */
class ContentController implements ControllerProviderInterface
{
    /**
     * @var Config
     */
    private $config;
    /**
     * @var APIHelper
     */
    private $APIHelper;

    /**
     * ContentController constructor.
     * @param Config $config
     * @param APIHelper $APIHelper
     */
    public function __construct(Config $config, APIHelper $APIHelper)
    {
        $this->config = $config;
        $this->APIHelper = $APIHelper;
    }

    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app)
    {
        /**
         * @var $ctr \Silex\ControllerCollection
         */
        $ctr = $app['controllers_factory'];

        $ctr->get("/menu", [$this, "listMenus"])->bind('jsonapi.menu');

        $ctr->get("/{contentType}/search", [$this, "searchContent"])
            ->bind('jsonapi.searchContent')
            ->convert('parameters', 'jsonapi.converter:convert');

        $ctr->get("/{contentType}/{slug}/{relatedContentType}", [$this, 'singleContent'])
            ->value('relatedContentType', null)
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('jsonapi.singleContent')
            ->convert('parameters', 'jsonapi.converter:convert');

        $ctr->get("/{contentType}", [$this, "getContentList"])
            ->bind('jsonapi.listContent')
            ->convert('parameters', 'jsonapi.converter:convert');

        return $ctr;
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param $contentType
     * @return ApiResponse
     */
    public function getContentList(Request $request, Application $app, $contentType, JSONAPIConverter $parameters)
    {
        $this->config->setCurrentRequest($request);

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config->getContentTypes()[$contentType]['where-clause'])) {
            //$query->andWhere('contentType.' . $key . ' LIKE :' . $parameterName . '');
            $where = $this->config->getContentTypes()[$contentType]['where-clause'];
            foreach ($where as $column => $value) {
                $filters = array_merge($parameters->getFilters(), [$column => $value]);
                $parameters->setFilters($filters);
            }
        }

        $where = array_merge($parameters->getFilters(), $parameters->getContains());

        $queryParameters = array_merge($where, ['order' => $parameters->getOrder()]);

        /** @var QueryResultset $results */
        $results = $app['query']->getContent($contentType, $queryParameters)->get($contentType);

        $totalItems = count($results);

        foreach ($parameters->getIncludes() as $ct) {
            // Check if the include exists in the contenttypes definition.
            $exists = $app['config']->get("contenttypes/$contentType/relations/$ct", false);
            if ($exists !== false) {
                foreach ($results as $key => $item) {
                    //$ct = $item->getSlug();
                    $ctAllFields = $this->APIHelper->getAllFieldNames($ct);
                    $ctFields = $this->APIHelper->getFields($ct, $ctAllFields, 'list-fields');
                    foreach ($item->relation[$ct] as $related) {
                        $included[$key] = $this->APIHelper->cleanItem($related, $ctFields);
                    }
                }
            }
        }

        $offset = ($parameters->getPage()-1)*$parameters->getLimit();

        $results = array_splice($results, $offset, $parameters->getLimit());

        if (! $results || count($results) === 0) {
            return new ApiInvalidRequestResponse([
                'detail' => "Bad request: There were no results based upon your criteria!"
            ], $this->config);
        }

        if (empty($results)) {
            $results = [];
        }

        $items = [];

        foreach ($results as $key => $item) {
            $allFields = $this->APIHelper->getAllFieldNames($contentType);
            $fields = $this->APIHelper->getFields($contentType, $allFields, 'list-fields');
            $items[$key] = $this->APIHelper->cleanItem($item, $fields);
        }

        $totalPages = ceil(($totalItems/$parameters->getLimit()) > 1 ? ($totalItems/$parameters->getLimit()) : 1);

        $response = [
            'links' => $this->APIHelper->makeLinks(
                $contentType,
                $parameters->getPage(),
                $totalPages,
                $parameters->getLimit()
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

    /**
     * @param Request $request
     * @param Application $app
     * @param $contentType
     * @return ApiResponse
     */
    public function searchContent(Request $request, Application $app, $contentType, JSONAPIConverter $parameters)
    {
        $this->config->setCurrentRequest($request);

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config->getContentTypes()[$contentType]['where-clause'])) {
            //$query->andWhere('contentType.' . $key . ' LIKE :' . $parameterName . '');
            $where = $this->config->getContentTypes()[$contentType]['where-clause'];
            foreach ($where as $column => $value) {
                $filters = array_merge($parameters->getFilters(), [$column => $value]);
                $parameters->setFilters($filters);
            }
        }

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
            return new ApiInvalidRequestResponse([
                'detail' => "No query parameter q specified."
            ], $this->config);
        }

        $where = array_merge($parameters->getFilters(), $parameters->getContains());

        $queryParameters = array_merge($where, ['order' => $parameters->getOrder(), 'filter' => $q]);

        /** @var QueryResultset $results */
        $results = $app['query']->getContent($baselink, $queryParameters)->get($contentType);

        $totalItems = count($results);

        $totalPages = ceil(($totalItems/$parameters->getLimit()) > 1 ? ($totalItems/$parameters->getLimit()) : 1);

        $offset = ($parameters->getPage()-1)*$parameters->getLimit();

        $results = array_splice($results, $offset, $parameters->getLimit());

        if (! $results || count($results) === 0) {
            return new ApiInvalidRequestResponse([
                'detail' => "No search results found for query [$q]"
            ], $this->config);
        }

        foreach ($results as $key => $item) {
            $ct = $item->getSlug();
            // optimize this part...
            $ctAllFields = $this->APIHelper->getAllFieldNames($ct);
            $ctFields = $this->APIHelper->getFields($ct, $ctAllFields, 'list-fields');
            $items[$key] = $this->APIHelper->cleanItem($item, $ctFields);
        }

        return new ApiResponse([
            'links' => $this->APIHelper->makeLinks(
                $baselink,
                $parameters->getPage(),
                $totalPages,
                $parameters->getLimit()
            ),
            'meta' => [
                "count" => count($items),
                "total" => $totalItems
            ],
            'data' => $items,
        ], $this->config);
    }


    /**
     * @param Request $request
     * @param Application $app
     * @param $contentType
     * @param $slug
     * @param $relatedContentType
     * @return ApiResponse
     */
    public function singleContent(Request $request, Application $app, $contentType, $slug, $relatedContentType, JSONAPIConverter $parameters)
    {
        $this->config->setCurrentRequest($request);

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config->getContentTypes()[$contentType]['where-clause'])) {
            //$query->andWhere('contentType.' . $key . ' LIKE :' . $parameterName . '');
            $where = $this->config->getContentTypes()[$contentType]['where-clause'];
            foreach ($where as $column => $value) {
                $filters = array_merge($parameters->getFilters(), [$column => $value]);
                $parameters->setFilters($filters);
            }
        }

        $where = array_merge($parameters->getFilters(), $parameters->getContains());

        $queryParameters = array_merge($where, ['order' => $parameters->getOrder(), 'returnsingle' => true, 'id' => $slug]);

        /** @var QueryResultset $results */
        $results = $app['query']->getContent($contentType, $queryParameters);

        if (! $results || count($results) === 0) {
            return new ApiNotFoundResponse([
                'detail' => "No [$contentType] found with id/slug: [$slug]."
            ], $this->config);
        }

        if ($relatedContentType !== null) {
            $relatedItemsTotal = $results->getRelation()->getField($relatedContentType)->count();
            if ($relatedItemsTotal <= 0) {
                return new ApiNotFoundResponse([
                    'detail' => "No related items of type [$relatedContentType] found for [$contentType] with id/slug: [$slug]."
                ], $this->config);
            }

            $allFields = $this->APIHelper->getAllFieldNames($relatedContentType);
            $fields = $this->APIHelper->getFields($relatedContentType, $allFields, 'list-fields');

            foreach ($results->relation[$relatedContentType] as $item) {
                $items[] = $this->APIHelper->cleanItem($item, $fields);
            }

            $response = new ApiResponse([
                'links' => [
                    'self' => $this->config->getBasePath() . "/$contentType/$slug/$relatedContentType" . $this->APIHelper->makeQueryParameters()
                ],
                'meta' => [
                    "count" => count($items),
                    "total" => count($items)
                ],
                'data' => $items
            ], $this->config);

        } else {
            $allFields = $this->APIHelper->getAllFieldNames($contentType);
            $fields = $this->APIHelper->getFields($contentType, $allFields, 'item-fields');
            $values = $this->APIHelper->cleanItem($results, $fields);
            //@todo get previous and next link
            $prev = $results->previous();
            $next = $results->next();

            $defaultQueryString = $this->APIHelper->makeQueryParameters();
            $links = [
                'self' => $values['links']['self'] . $defaultQueryString
            ];

            // optional: This adds additional relationships links in the root
            //           variable 'links'.
            $related = $this->APIHelper->makeRelatedLinks($results);
            foreach ($related as $ct => $link) {
                $links[$ct] = $link;
            }

            foreach ($parameters->getIncludes() as $ct) {
                // Check if the include exists in the contenttypes definition.
                $exists = $app['config']->get("contenttypes/$contentType/relations/$ct", false);
                if ($exists !== false) {
                    foreach ($results as $key => $item) {
                        //$ct = $item->getSlug();
                        $ctAllFields = $this->APIHelper->getAllFieldNames($ct);
                        $ctFields = $this->APIHelper->getFields($ct, $ctAllFields, 'list-fields');
                        foreach ($item->relation[$ct] as $related) {
                            $included[$key] = $this->APIHelper->cleanItem($related, $ctFields);
                        }
                    }
                }
            }

            if ($prev) {
                $links['prev'] = sprintf('%s/%s/%d%s', $this->config->getBasePath(),
                    $contentType, $prev->values['id'], $defaultQueryString);
            }
            if ($next) {
                $links['next'] = sprintf('%s/%s/%d%s', $this->config->getBasePath(),
                    $contentType, $next->values['id'], $defaultQueryString);
            }

            $response = [
                'links' => $links,
                'data' => $values,
            ];

            if (!empty($included)) {
                $response['included'] = $included;
            }

            $response = new ApiResponse($response, $this->config);
        }

        return $response;
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function listMenus(Request $request, Application $app)
    {
        $this->config->setCurrentRequest($request);

        $name = '';
        if ($q = $request->get('q')) {
            $name = "/$q";
        }

        $menu = $app['config']->get('menu'.$name, false);
        if ($menu) {
            return new ApiResponse([
                'data' => $menu
            ], $this->config);
        }
        return new ApiNotFoundResponse([
            'detail' => "Menu with name [$q] not found."
        ], $this->config);
    }

}