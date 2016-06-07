<?php
namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Content;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\JSONAPIConverter;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterInterface;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiInvalidRequestResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Storage\Query\QueryResultset;
use Doctrine\Common\Collections\Collection;
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
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get("/{contentType}/{slug}/{relatedContentType}", [$this, 'singleContent'])
            ->value('relatedContentType', null)
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('jsonapi.singleContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get("/{contentType}", [$app['jsonapi.action.contentlist'], "handle"])
            ->bind('jsonapi.listContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        return $ctr;
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param $contentType
     * @param Collection|ParameterInterface[] $parameters
     * @return ApiResponse
     */
    public function searchContent(Request $request, Application $app, $contentType, ParameterCollection $parameters)
    {
        $this->config->setCurrentRequest($request);

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


    /**
     * @param Request $request
     * @param Application $app
     * @param $contentType
     * @param $slug
     * @param $relatedContentType
     * @param Collection|ParameterInterface[] $parameters
     * @return ApiResponse
     */
    public function singleContent(Request $request, Application $app, $contentType, $slug, $relatedContentType, ParameterCollection $parameters)
    {
        $this->config->setCurrentRequest($request);

        $queryParameters = array_merge($parameters->getQueryParameters(), ['returnsingle' => true, 'id' => $slug]);

        /** @var QueryResultset $results */
        $results = $app['query']->getContent($contentType, $queryParameters);

        if (! $results || count($results) === 0) {
            throw new ApiNotFoundException(
                "No [$contentType] found with id/slug: [$slug]."
            );
        }

        if ($relatedContentType !== null) {
            $relatedItemsTotal = $results->getRelation()->getField($relatedContentType)->count();
            if ($relatedItemsTotal <= 0) {
                throw new ApiNotFoundException(
                    "No related items of type [$relatedContentType] found for [$contentType] with id/slug: [$slug]."
                );
            }

            //$allFields = $this->APIHelper->getAllFieldNames($relatedContentType);
            //$fields = $this->APIHelper->getFields($relatedContentType, $allFields, 'list-fields');
            // @todo item-fields INSTEAD of list-items
            $fields = $parameters->get('fields')->getFields();

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
            //$allFields = $this->APIHelper->getAllFieldNames($contentType);
            //$fields = $this->APIHelper->getFields($contentType, $allFields, 'item-fields');
            $fields = $parameters->get('fields')->getFields();
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

            $includes = $parameters->getParametersByType('includes');

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

        throw new ApiNotFoundException(
            "Menu with name [$q] not found."
        );

    }

}