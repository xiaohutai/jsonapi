<?php
namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Content;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiInvalidRequestResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
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

        $ctr->get("/{contentType}", [$this, "getContentList"])
            ->bind('jsonapi.listContent');

        $ctr->get("/{contentType}/search", [$this, "searchContent"])
            ->bind('jsonapi.searchContent');

        $ctr->get("/{contentType}/{slug}/{relatedContentType}", [$this, 'singleContent'])
            ->value('relatedContentType', null)
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('jsonapi.singleContent');

        return $ctr;
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param $contentType
     * @return ApiResponse
     */
    public function getContentList(Request $request, Application $app, $contentType)
    {
        $this->config->setCurrentRequest($request);
        $this->APIHelper->fixBoltStorageRequest();

        if (!array_key_exists($contentType, $this->config->getContentTypes())) {
            return new ApiNotFoundResponse([
                'detail' => "Contenttype with name [$contentType] not found."
            ], $this->config);
        }

        $options = [];
        if ($limit = $request->get('limit')) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }

        if ($page = $request->get('page')) {
            $page = intval($page);
            if ($page >= 1) {
                $options['page'] = $page;
            }
        }
        if ($order = $request->get('sort')) {
            $options['order'] = $order;
        }

        // Enable pagination
        $options['paging'] = true;
        $pager = [];
        $where = [];

        $allFields = $this->APIHelper->getAllFieldNames($contentType);
        $fields = $this->APIHelper->getFields($contentType, $allFields, 'list-fields');

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config->getContentTypes()[$contentType]['where-clause'])) {
            $where = $this->config->getContentTypes()['contenttypes'][$contentType]['where-clause'];
        }

        // Handle $filter[], this modifies the $where[] clause.
        if ($filters = $request->get('filter')) {
            foreach ($filters as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return new ApiInvalidRequestResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contentType]."
                    ], $this->config);
                }
                // A bit crude for now.
                $where[$key] = str_replace(',', ' || ', $value);
            }
        }

        // Handle $contains[], this modifies the $where[] clause to search using Like.
        if ($contains = $request->get('contains')) {
            foreach ($contains as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return new ApiInvalidRequestResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contentType]."
                    ], $this->config);
                }

                $values = explode(",", $value);

                foreach ($values as $i => $item) {
                    $values[$i] = '%' . $item . '%';
                }

                $where[$key] = implode(' || ', $values);
            }
        }

        // If `returnsingle` is not set to false, then a single result will not
        // result in an array.
        $where['returnsingle'] = false;
        $items = $app['storage']->getContent($contentType, $options, $pager, $where);

        // If we don't have any items, this can mean one of two things: either
        // the contenttype does not exist (in which case we'll get a non-array
        // response), or it exists, but no content has been added yet.

        if (!is_array($items)) {
            return new ApiInvalidRequestResponse([
                'detail' => "Configuration error: [$contentType] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ], $this->config);
        }

        if (empty($items)) {
            $items = [];
        }

        $items = array_values($items);

        // Handle "include" and fetch related relationships in current query.
        try {
            $included = $this->APIHelper->fetchIncludedContent($contentType, $items);
        } catch (\Exception $e) {
            return new ApiInvalidRequestResponse([
                'detail' => $e->getMessage()
            ], $this->config);
        }

        foreach ($items as $key => $item) {
            $items[$key] = $this->APIHelper->cleanItem($item, $fields);
        }

        $response = [
            'links' => $this->APIHelper->makeLinks($contentType, $pager['current'], intval($pager['totalpages']), $limit),
            'meta' => [
                "count" => count($items),
                "total" => intval($pager['count'])
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
    public function searchContent(Request $request, Application $app, $contentType)
    {
        $this->config->setCurrentRequest($request);
        $this->APIHelper->fixBoltStorageRequest();

        $options = [];
        $options['paging'] = true;
        $pager = [];

        if ($limit = $request->get($this->config->getPaginationSizeKey())) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }

        if ($page = $request->get($this->config->getPaginationNumberKey())) {
            $page = intval($page);
        }
        if (!$page) {
            $page = 1;
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

        if ($q = $request->get('q')) {
            $options['filter'] = $q;
        } else {
            return new ApiInvalidRequestResponse([
                'detail' => "No query parameter q specified."
            ], $this->config);
        }

        // This 'page' part somehow messses with the getContent query. The
        // fetched results get sliced one time too much when using pagination.
        // So we unset it here, and then use array_slice.
        $all = $request->query->all();
        unset($all['page']);
        $request->query->replace($all);

        $items = $app['storage']->getContent($contentType . '/search', ['filter' => $q], $pager, ['returnsingle' => false]);

        if (!is_array($items)) {
            return new ApiInvalidRequestResponse([
                'detail' => "Configuration error: [$contentType] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ], $this->config);
        }

        if (empty($items)) {
            return new ApiNotFoundResponse([
                'detail' => "No search results found for query [$q]"
            ], $this->config);
        }

        $total = count($items);
        $totalpages = $limit > 0 ? intval(ceil($total / $limit)) : 1;

        if ($limit && $page) {
            $items = array_slice($items, $limit * ($page - 1), $limit);
        }

        // Reset it again...
        $all = $request->query->all();
        if ($page != $totalpages) {
            $all['page'] = $page;
        }
        $request->query->replace($all);

        foreach ($items as $key => $item) {
            $ct = $item->contenttype['slug'];
            // optimize this part...
            $ctAllFields = $this->APIHelper->getAllFieldNames($ct);
            $ctFields = $this->APIHelper->getFields($ct, $ctAllFields, 'list-fields');
            $items[$key] = $this->APIHelper->cleanItem($item, $ctFields);
        }

        return new ApiResponse([
            'links' => $this->APIHelper->makeLinks($baselink, $page, $totalpages, $limit),
            'meta' => [
                "count" => count($items),
                "total" => $total
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
    public function singleContent(Request $request, Application $app, $contentType, $slug, $relatedContentType)
    {
        $this->config->setCurrentRequest($request);

        if (!array_key_exists($contentType, $this->config->getContentTypes())) {
            return new ApiNotFoundResponse([
                'detail' => "Contenttype with name [$contentType] not found."
            ], $this->config);
        }

        /** @var Content $item */
        $item = $app['storage']->getContent("$contentType/$slug");
        if (!$item) {
            return new ApiNotFoundResponse([
                'detail' => "No [$contentType] found with id/slug: [$slug]."
            ], $this->config);
        }

        if ($relatedContentType !== null) {
            $items = $item->related($relatedContentType);
            if (!$items) {
                return new ApiNotFoundResponse([
                    'detail' => "No related items of type [$relatedContentType] found for [$contentType] with id/slug: [$slug]."
                ], $this->config);
            }

            $allFields = $this->APIHelper->getAllFieldNames($relatedContentType);
            $fields = $this->APIHelper->getFields($relatedContentType, $allFields, 'list-fields');

            $items = array_values($items);
            foreach ($items as $key => $item) {
                $items[$key] = $this->APIHelper->cleanItem($item, $fields);
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
            $values = $this->APIHelper->cleanItem($item, $fields);
            $prev = $item->previous();
            $next = $item->next();

            $defaultQueryString = $this->APIHelper->makeQueryParameters();
            $links = [
                'self' => $values['links']['self'] . $defaultQueryString
            ];

            // optional: This adds additional relationships links in the root
            //           variable 'links'.
            $related = $this->APIHelper->makeRelatedLinks($item);
            foreach ($related as $ct => $link) {
                $links[$ct] = $link;
            }

            try {
                $included = $this->APIHelper->fetchIncludedContent($contentType, [$item]);
            } catch (\Exception $e) {
                return new ApiInvalidRequestResponse([
                    'detail' => $e->getMessage()
                ], $this->config);
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

}