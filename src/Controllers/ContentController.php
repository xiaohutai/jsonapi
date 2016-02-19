<?php
namespace JSONAPI\Controllers;

use Bolt\Content;
use JSONAPI\Config\Config;
use JSONAPI\Helpers\APIHelper;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ContentController
 * @package JSONAPI\Controllers
 */
class ContentController extends APIController implements ControllerProviderInterface
{

    /**
     * @var Application
     */
    private $app;

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
     * @param Application $app
     */
    public function __construct(Config $config, APIHelper $APIHelper, Application $app)
    {
        $this->app = $app;
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

        $ctr->get("", [$this, "getContentList"]);
        $ctr->get("/search", [$this, "searchContent"]);
        $ctr->get("/{slug}/{relatedContenttype}", [$this, 'singleContent'])
            ->value('relatedContenttype', null)
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('jsonapi');

        return $ctr;
    }

    /**
     * @param $contenttype
     * @param Request $request
     * @return JsonResponse
     */
    public function getContentList($contenttype, Request $request)
    {

        $this->config->setCurrentRequest($request);
        $this->APIHelper->fixBoltStorageRequest();

        if (!array_key_exists($contenttype, $this->config->getContentTypes())) {
            return new JsonResponse([
                'detail' => "Contenttype with name [$contenttype] not found."
            ]);
        }

        $options = [];
        if ($limit = $request->get('page')) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }

        if ($page = $request->get('limit')) {
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

        $allFields = $this->APIHelper->getAllFieldNames($contenttype);
        $fields = $this->APIHelper->getFields($contenttype, $allFields, 'list-fields');

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config->getContentTypes()[$contenttype]['where-clause'])) {
            $where = $this->config->getContentTypes()['contenttypes'][$contenttype]['where-clause'];
        }

        // Handle $filter[], this modifies the $where[] clause.
        if ($filters = $request->get('filter')) {
            foreach ($filters as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return new JsonResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contenttype]."
                    ]);
                }
                // A bit crude for now.
                $where[$key] = str_replace(',', ' || ', $value);
            }
        }

        // Handle $contains[], this modifies the $where[] clause to search using Like.
        if ($contains = $request->get('contains')) {
            foreach ($contains as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return new JsonResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contenttype]."
                    ]);
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
        $items = $this->app['storage']->getContent($contenttype, $options, $pager, $where);

        // If we don't have any items, this can mean one of two things: either
        // the contenttype does not exist (in which case we'll get a non-array
        // response), or it exists, but no content has been added yet.

        if (!is_array($items)) {
            return new JsonResponse([
                'detail' => "Configuration error: [$contenttype] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ]);
        }

        if (empty($items)) {
            $items = [];
        }

        $items = array_values($items);

        // Handle "include" and fetch related relationships in current query.
        try {
            $included = $this->APIHelper->fetchIncludedContent($contenttype, $items);
        } catch (\Exception $e) {
            return new JsonResponse([
                'detail' => $e->getMessage()
            ]);
        }

        foreach ($items as $key => $item) {
            $items[$key] = $this->APIHelper->cleanItem($item, $fields);
        }

        $response = [
            'links' => $this->APIHelper->makeLinks($contenttype, $pager['current'], intval($pager['totalpages']), $limit),
            'meta' => [
                "count" => count($items),
                "total" => intval($pager['count'])
            ],
            'data' => $items,
        ];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        return new JsonResponse($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function searchContent(Request $request, $contenttype)
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
        $baselink = "$contenttype/search";
        if ($contenttype === null) {
            $allcontenttypes = array_keys($this->config->getContentTypes());
            // This also fetches unallowed ones:
            // $allcontenttypes = array_keys($this->app['config']->get('contenttypes'));
            $allcontenttypes = implode(',', $allcontenttypes);
            $contenttype = "($allcontenttypes)";
            $baselink = 'search';
        }

        if ($q = $request->get('q')) {
            $options['filter'] = $q;
        } else {
            return new JsonResponse([
                'detail' => "No query parameter q specified."
            ]);
        }

        // This 'page' part somehow messses with the getContent query. The
        // fetched results get sliced one time too much when using pagination.
        // So we unset it here, and then use array_slice.
        $all = $request->query->all();
        unset($all['page']);
        $request->query->replace($all);

        $items = $this->app['storage']->getContent($contenttype . '/search', ['filter' => $q], $pager, ['returnsingle' => false]);

        if (!is_array($items)) {
            return new JsonResponse([
                'detail' => "Configuration error: [$contenttype] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ]);
        }

        if (empty($items)) {
            return new JsonResponse([
                'detail' => "No search results found for query [$q]"
            ]);
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

        return new JsonResponse([
            'links' => $this->APIHelper->makeLinks($baselink, $page, $totalpages, $limit),
            'meta' => [
                "count" => count($items),
                "total" => $total
            ],
            'data' => $items,
        ]);
    }


    public function singleContent(Request $request, $contenttype, $slug, $relatedContenttype)
    {
        $this->config->setCurrentRequest($request);

        if (!array_key_exists($contenttype, $this->config->getContentTypes())) {
            return new JsonResponse([
                'detail' => "Contenttype with name [$contenttype] not found."
            ]);
        }

        /** @var Content $item */
        $item = $this->app['storage']->getContent("$contenttype/$slug");
        if (!$item) {
            return new JsonResponse([
                'detail' => "No [$contenttype] found with id/slug: [$slug]."
            ]);
        }

        if ($relatedContenttype !== null) {
            $items = $item->related($relatedContenttype);
            if (!$items) {
                return new JsonResponse([
                    'detail' => "No related items of type [$relatedContenttype] found for [$contenttype] with id/slug: [$slug]."
                ]);
            }

            $allFields = $this->APIHelper->getAllFieldNames($relatedContenttype);
            $fields = $this->APIHelper->getFields($relatedContenttype, $allFields, 'list-fields');

            $items = array_values($items);
            foreach ($items as $key => $item) {
                $items[$key] = $this->APIHelper->cleanItem($item, $fields);
            }

            $response = new JsonResponse([
                'links' => [
                    'self' => $this->config->getBasePath() . "/$contenttype/$slug/$relatedContenttype" . $this->APIHelper->makeQueryParameters()
                ],
                'meta' => [
                    "count" => count($items),
                    "total" => count($items)
                ],
                'data' => $items
            ]);

        } else {

            $allFields = $this->APIHelper->getAllFieldNames($contenttype);
            $fields = $this->APIHelper->getFields($contenttype, $allFields, 'item-fields');
            $values = $this->APIHelper->cleanItem($item, $fields);
            $prev = $item->previous();
            $next = $item->next();

            $defaultQuerystring = $this->APIHelper->makeQueryParameters();
            $links = [
                'self' => $values['links']['self'] . $defaultQuerystring
            ];

            // optional: This adds additional relationships links in the root
            //           variable 'links'.
            $related = $this->APIHelper->makeRelatedLinks($item);
            foreach ($related as $ct => $link) {
                $links[$ct] = $link;
            }

            try {
                $included = $this->APIHelper->fetchIncludedContent($contenttype, [$item]);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'detail' => $e->getMessage()
                ]);
            }

            if ($prev) {
                $links['prev'] = sprintf('%s/%s/%d%s', $this->config->getBasePath(),
                    $contenttype, $prev->values['id'], $defaultQuerystring);
            }
            if ($next) {
                $links['next'] = sprintf('%s/%s/%d%s', $this->config->getBasePath(),
                    $contenttype, $next->values['id'], $defaultQuerystring);
            }

            $response = [
                'links' => $links,
                'data' => $values,
            ];

            if (!empty($included)) {
                $response['included'] = $included;
            }

            $response = new JsonResponse($response);
        }

        return $response;
    }

}