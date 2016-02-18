<?php
/**
 * JSON API extension for Bolt. Forked from the JSONAccess extension.
 *
 * @author Tobias Dammers <tobias@twokings.nl>
 * @author Bob den Otter <bob@twokings.nl>
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */

namespace JSONAPI;

use \Bolt\Helpers\Arr;
use JSONAPI\Controllers\ContentController;
use JSONAPI\Helpers\APIHelper;
use JSONAPI\Helpers\ConfigHelper;
use JSONAPI\Provider\APIProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 */
class Extension extends \Bolt\BaseExtension
{

    /**
     * @var Request
     */
    public static $request;


    public static $base = '/json';
    public static $basePath;

    public static $paginationNumberKey = 'page'; // todo: page[number]
    public static $paginationSizeKey = 'limit';  // todo: page[size]

    /**
     * Returns the name of this extension.
     *
     * @return string The name of this extension.
     */
    public function getName()
    {
        return "JSON API";
    }

    public function initialize()
    {

        $this->app->register(new APIProvider($this->config));


        $this->app->mount($this->app['jsonapi.config']->getBase()."/{contenttype}",
            new ContentController($this->app['jsonapi.config'], $this->app['jsonapi.apihelper'], $this->app));


     /*   $this->app->get($this->base."/{contenttype}/search", [$this, 'jsonapi_search'])
                  ->bind('jsonapi_search');
        $this->app->get($this->base."/{contenttype}/{slug}/{relatedContenttype}", [$this, 'jsonapi'])
                  ->value('relatedContenttype', null)
                  ->assert('slug', '[a-zA-Z0-9_\-]+')
                  ->bind('jsonapi');
        $this->app->get($this->base."/{contenttype}", [$this, 'jsonapi_list'])
                  ->bind('jsonapi_list');*/
    }

    // -------------------------------------------------------------------------
    // -- FUNCTIONS HANDLING REQUESTS                                         --
    // -------------------------------------------------------------------------

    /**
     * Fetches records of the specified $contenttype.
     *
     * @param Request $request
     * @param string $contenttype The name of the contenttype.
     */
    public function jsonapi_list(Request $request, $contenttype)
    {
        APIInstance::$request = $request;

        $this->fixBoltStorageRequest();

        if (!array_key_exists($contenttype, $this->config['contenttypes'])) {
            return $this->responseNotFound([
                'detail' => "Contenttype with name [$contenttype] not found."
            ]);
        }
        $options = [];
        if ($limit = $request->get($this->paginationSizeKey)) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }
        if ($page = $request->get($this->paginationNumberKey)) {
            $page = intval($page);
            if ($page >= 1) {
                $options['page'] = $page;
            }
        }
        if ($order = $request->get('sort')) {
            // $order = explode(',' $order);
            // Bolt currently does NOT support multiple sortorders:
            //
            // @see \Bolt\Storage::decodeContentQuery()
            // @see \Bolt\Storage::getEscapedSortorder()
            // @see \Bolt\Storage::getSortOrder()
            $options['order'] = $order;
        }

        // Enable pagination
        $options['paging'] = true;
        $pager  = [];
        $where  = [];

        $allFields = $this->getAllFieldNames($contenttype);
        $fields = $this->getFields($contenttype, $allFields, 'list-fields');

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config['contenttypes'][$contenttype]['where-clause'])) {
            $where = $this->config['contenttypes'][$contenttype]['where-clause'];
        }

        // Handle $filter[], this modifies the $where[] clause.
        if ($filters = $request->get('filter')) {
            foreach($filters as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return $this->responseInvalidRequest([
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
                    return $this->responseInvalidRequest([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contenttype]."
                    ]);
                }

                $values = explode(",", $value);

                foreach ($values as $i => $item) {
                    $values[$i] = '%' . $item .'%';
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
            return $this->responseInvalidRequest([
                'detail' => "Configuration error: [$contenttype] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ]);
        }

        if (empty($items)) {
            $items = [];
        }

        $items = array_values($items);

        // Handle "include" and fetch related relationships in current query.
        try {
            $included = $this->fetchIncludedContent($contenttype, $items);
        } catch(\Exception $e) {
            return $this->responseInvalidRequest([
                'detail' => $e->getMessage()
            ]);
        }

        foreach($items as $key => $item) {
            $items[$key] = $this->cleanItem($item, $fields);
        }

        $response = [
            'links' => $this->makeLinks($contenttype, $pager['current'], intval($pager['totalpages']), $limit),
            'meta' => [
                "count" => count($items),
                "total" => intval($pager['count'])
            ],
            'data' => $items,
        ];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        return $this->response($response);
    }

    /**
     * Fetches a single item or all related items — of which their contenttype is
     * defined in $relatedContenttype — of that single item.
     *
     * @todo split up fetching single item and fetching of related items?
     *
     * @param Request $request
     * @param string $contenttype The name of the contenttype.
     * @param string $slug The slug, preferably a numeric id, but Bolt allows
     *                     slugs in the form of strings as well.
     * @param string $relatedContenttype The name of the related contenttype
     *                                   that is related to $contenttype.
     */
    public function jsonapi(Request $request, $contenttype, $slug, $relatedContenttype)
    {
        $this->request = $request;

        if (!array_key_exists($contenttype, $this->config['contenttypes'])) {
            return $this->responseNotFound([
                'detail' => "Contenttype with name [$contenttype] not found."
            ]);
        }

        $item = $this->app['storage']->getContent("$contenttype/$slug");
        if (!$item) {
            return $this->responseNotFound([
                'detail' => "No [$contenttype] found with id/slug: [$slug]."
            ]);
        }

        if ($relatedContenttype !== null)
        {

            // If a $relatedContenttype is set, fetch the related items.

            $items = $item->related($relatedContenttype);
            if (!$items) {
                return $this->responseNotFound([
                    'detail' => "No related items of type [$relatedContenttype] found for [$contenttype] with id/slug: [$slug]."
                ]);
            }

            $allFields = $this->getAllFieldNames($relatedContenttype);
            $fields = $this->getFields($relatedContenttype, $allFields, 'list-fields');

            $items = array_values($items);
            foreach($items as $key => $item) {
                $items[$key] = $this->cleanItem($item, $fields);
            }

            $response = $this->response([
                'links' => [
                    'self' => "$this->basePath/$contenttype/$slug/$relatedContenttype" . $this->makeQueryParameters()
                ],
                'meta' => [
                    "count" => count($items),
                    "total" => count($items)
                ],
                'data' => $items
            ]);

        } else {

            // Fetch a single item only.

            $allFields = $this->getAllFieldNames($contenttype);
            $fields = $this->getFields($contenttype, $allFields, 'item-fields');
            $values = $this->cleanItem($item, $fields);
            $prev = $item->previous();
            $next = $item->next();

            $defaultQuerystring = $this->makeQueryParameters();
            $links = [
                'self' => $values['links']['self'] . $defaultQuerystring
            ];

            // optional: This adds additional relationships links in the root
            //           variable 'links'.
            $related = $this->makeRelatedLinks($item);
            foreach ($related as $ct => $link) {
                $links[$ct] = $link;
            }

            try {
                $included = $this->fetchIncludedContent($contenttype, [ $item ]);
            } catch(\Exception $e) {
                return $this->responseInvalidRequest([
                    'detail' => $e->getMessage()
                ]);
            }

            if ($prev)  {
                $links['prev'] = sprintf('%s/%s/%d%s', $this->basePath, $contenttype, $prev->values['id'], $defaultQuerystring);
            }
            if ($next) {
                $links['next'] = sprintf('%s/%s/%d%s', $this->basePath, $contenttype, $next->values['id'], $defaultQuerystring);
            }

            $response = [
                'links' => $links,
                'data' => $values,
            ];

            if (!empty($included)) {
                $response['included'] = $included;
            }

            $response = $this->response($response);
        }

        return $response;
    }

    /**
     * @todo links, meta etc.
     *
     * @param Request $request
     * @param string $contenttype The name of the specific $contenttype to search
     *                             in, otherwise search all contenttypes.
     */
    public function jsonapi_search(Request $request, $contenttype = null)
    {
        $this->request = $request;
        $this->fixBoltStorageRequest();

        $options = [];
        $options['paging'] = true;
        $pager = [];

        if ($limit = $request->get($this->paginationSizeKey)) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }

        if ($page = $request->get($this->paginationNumberKey)) {
            $page = intval($page);
        }
        if (!$page) {
            $page = 1;
        }

        // If no $contenttype is set, search all 'searchable' contenttypes.
        $baselink = "$contenttype/search";
        if ($contenttype === null) {
            $allcontenttypes = array_keys($this->config['contenttypes']);
            // This also fetches unallowed ones:
            // $allcontenttypes = array_keys($this->app['config']->get('contenttypes'));
            $allcontenttypes = implode(',', $allcontenttypes);
            $contenttype = "($allcontenttypes)";
            $baselink = 'search';
        }

        if ($q = $request->get('q')) {
            $options['filter'] = $q;
        } else {
            return $this->responseInvalidRequest([
                'detail' => "No query parameter q specified."
            ]);
        }

        // This 'page' part somehow messses with the getContent query. The
        // fetched results get sliced one time too much when using pagination.
        // So we unset it here, and then use array_slice.
        $all = $request->query->all();
        unset($all['page']);
        $request->query->replace($all);

        $items = $this->app['storage']->getContent($contenttype.'/search', [ 'filter' => $q ], $pager, [ 'returnsingle' => false ]);

        if (!is_array($items)) {
            return $this->responseInvalidRequest([
                'detail' => "Configuration error: [$contenttype] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ]);
        }

        if (empty($items)) {
            return $this->responseNotFound([
                'detail' => "No search results found for query [$q]"
            ]);
        }

        $total = count($items);
        $totalpages = $limit > 0 ? intval(ceil($total / $limit )) : 1;

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
            $ctAllFields = $this->getAllFieldNames($ct);
            $ctFields = $this->getFields($ct, $ctAllFields, 'list-fields');
            $items[$key] = $this->cleanItem($item, $ctFields);
        }

        return $this->response([
            'links' => $this->makeLinks($baselink, $page, $totalpages, $limit),
            'meta' => [
                "count" => count($items),
                "total" => $total
            ],
            'data' => $items,
        ]);
    }

    /**
     * Fetches menus. Either a list of menus, or a single menu defined by the
     * query string `q`.
     *
     * @todo fetch all the records from the database.
     *
     * @param Request $request
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function jsonapi_menu(Request $request)
    {
        $this->request = $request;

        $name = '';

        if ($q = $request->get('q')) {
            $name = "/$q";
        }

        $menu = $this->app['config']->get('menu'.$name, false);

        if ($menu) {
            return $this->response([
                'data' => $menu
            ]);
        }

        return $this->responseNotFound([
            'detail' => "Menu with name [$q] not found."
        ]);
    }

    // -------------------------------------------------------------------------
    // -- HELPER FUNCTIONS                                                    --
    // -------------------------------------------------------------------------
}
