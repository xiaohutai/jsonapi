<?php
namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Content;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiInvalidRequestResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
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
        //$this->APIHelper->fixBoltStorageRequest();

        if (!array_key_exists($contentType, $this->config->getContentTypes())) {
            return new ApiNotFoundResponse([
                'detail' => "Contenttype with name [$contentType] not found."
            ], $this->config);
        }

        $repository = $app['storage']->getRepository($contentType);
        $query = $repository->createQueryBuilder('contentType');
        $query2 = $repository->createQueryBuilder('contentType');
        $query2->select('COUNT(*) AS total');

        $limit = intval($request->query->get('page[size]', 10, true));
        $limit = $limit >= 1 ? $limit : 10;
        $query->setMaxResults($limit);

        $page = intval($request->query->get('page[number]', 1, true));
        $page = $page >= 1 ? $page : 1;
        $query->setFirstResult($limit * ($page - 1));

        $orders = explode(',', $request->get('sort', 'id'));
        if ($orders) {
            foreach ($orders as $order) {
                $order = ltrim($order);
                $sort = ltrim($order, '-');
                $query->addOrderBy('contentType.' . $sort, ('-' === $order[0] ? 'DESC' : 'ASC'));
            }
        }

        // Enable pagination
        //$options['paging'] = true;
        $pager = [];
        $where = [];

        $allFields = $this->APIHelper->getAllFieldNames($contentType);
        $fields = $this->APIHelper->getFields($contentType, $allFields, 'list-fields');

        // Use the `where-clause` defined in the contenttype config.
        // @todo get default where parameters
        if (isset($this->config->getContentTypes()[$contentType]['where-clause'])) {
            //$query->andWhere('contentType.' . $key . ' LIKE :' . $parameterName . '');
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

                $value = explode(',', $value);
                $newValues = '\'' . implode('\',\'', $value) . '\'';
                $query->orWhere('contentType.' . $key . ' IN(:' . $key . ')');
                $query2->orWhere('contentType.' . $key . ' IN(:' . $key . ')');
                $query->setParameter($key, $newValues);
                $query2->setParameter($key, $newValues);

                // A bit crude for now.
                //$where[$key] = str_replace(',', ' || ', $value);
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

                $values = explode(',', $value);

                foreach ($values as $value) {
                    $parameterName = '';

                    //Random Parameter Name Generator LOL!!!!
                    for ($i = 0; $i < 6; $i++) {
                        $parameterName .= chr(rand(ord('a'), ord('z')));
                    }

                    $query->orWhere('contentType.' . $key . ' LIKE :' . $parameterName . '');
                    $query2->orWhere('contentType.' . $key . ' LIKE :' . $parameterName . '');
                    $query->setParameter($parameterName, $value);
                    $query2->setParameter($parameterName, $value);
                }

                //$values = explode(",", $value);

                /*foreach ($values as $i => $item) {
                    $values[$i] = '%' . $item . '%';
                }

                $where[$key] = implode(' || ', $values);*/
            }
        }

        $totalItems = 0;

        $totalItemsQuery = $query2->execute()->fetch();
        if (isset($totalItemsQuery['total'])) {
            $totalItems = intval($totalItemsQuery['total']);
        }

        $items = $repository->findWith($query);

        // Handle "include" and fetch related relationships in current query.
        try {
            $include = $this->APIHelper->getContenttypesToInclude();

            foreach($include as $ct) {
                // Check if the include exists in the contenttypes definition.
                $exists = $app['config']->get("contenttypes/$contentType/relations/$ct", false);
                if ($exists !== false) {
                    $toFetch[$ct] = [];

                    foreach ($items as $item) {
                        if (count($item->getRelation()->getField($ct)) >= 1) {
                            /*$test = $item->getRelation()->getField($ct);
                            $criteria = Criteria::create();
                            $criteria->where(Criteria::expr()->eq('to_contenttype', $ct));
                            $matched = $item->getRelation()->matching($criteria);*/

                            foreach ($item->getRelation()->getField($ct) as $related) {
                                $test = $related->getField('pages');
                                $toFetch[$ct][] = $related->getId();
                            }
                        }
                    }
                }
            }

            if (isset($toFetch)) {
                $includedRepository = $app['storage']->getRepository($ct);
                $related = $includedRepository->findBy(['id' => $toFetch[$ct]]);
                foreach(array_values($related) as $key => $item) {
                    // todo: optimize dynamically!
                    $ct = $item->getSlug();
                    $ctAllFields = $this->APIHelper->getAllFieldNames($ct);
                    $ctFields = $this->APIHelper->getFields($ct, $ctAllFields, 'list-fields');
                    $included[$key] = $this->APIHelper->cleanItem($item, $ctFields);
                }
            }


            //$included = $this->APIHelper->fetchIncludedContent($contentType, $items);
        } catch (\Exception $e) {
            return new ApiInvalidRequestResponse([
                'detail' => $e->getMessage()
            ], $this->config);
        }

        // If `returnsingle` is not set to false, then a single result will not
        // result in an array.
        //$where['returnsingle'] = false;

        //$items = $app['storage']->getContent($contentType, $options, $pager, $where);

        //$items = $repository->findWith($query);

        // If we don't have any items, this can mean one of two things: either
        // the contenttype does not exist (in which case we'll get a non-array
        // response), or it exists, but no content has been added yet.

        if (!is_array($items)) {
            return new ApiInvalidRequestResponse([
                'detail' => "Bad request: There were no results based upon your criteria!"
                //'detail' => "Configuration error: [$contentType] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ], $this->config);
        }

        if (empty($items)) {
            $items = [];
        }

        $items = array_values($items);

        foreach ($items as $key => $item) {
            $items[$key] = $this->APIHelper->cleanItem($item, $fields);
        }

        $totalPages = ($totalItems/$limit) > 1 ? ($totalItems/$limit) : 1;

        $response = [
            'links' => $this->APIHelper->makeLinks($contentType, $page, $totalPages, $limit),
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
    public function searchContent(Request $request, Application $app, $contentType)
    {
        $this->config->setCurrentRequest($request);
        $this->APIHelper->fixBoltStorageRequest();

        $options = [];
        $options['paging'] = true;
        $pager = [];

        $repository = $app['storage']->getRepository($contentType);
        $search = $app['query.search'];
        $query = $repository->createQueryBuilder('contentType');

        $limit = intval($request->query->get('page[size]', 10, true));
        $limit = $limit >= 1 ? $limit : 10;
        //$query->setMaxResults($limit);

        $page = intval($request->query->get('page[number]', 1, true));
        $page = $page >= 1 ? $page : 1;
        //$query->setFirstResult($limit * ($page - 1));

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

        $search->setContentType($contentType);
        $search->setSearch($q);

        //$test = $app['query.search']->

        //$items = $app['storage']->searchContent('Lorem');

        //$items = $app['storage']->getRepository($contentType)->findWith($search);


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