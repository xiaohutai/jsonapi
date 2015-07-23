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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 */
class Extension extends \Bolt\BaseExtension
{

    private $base = '/json';
    private $basePath;
    private $paginationNumberKey = 'page'; // todo: page[number]
    private $paginationSizeKey = 'limit';  // todo: page[size]

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
        if(isset($this->config['base'])) {
            $this->base = $this->config['base'];
        }
        $this->basePath = $this->app['paths']['canonical'] . $this->base;

        $this->app->get($this->base."/menu", [$this, 'jsonapi_menu'])
                  ->bind('jsonapi_menu');
        $this->app->get($this->base."/search", [$this, 'jsonapi_search'])
                  ->bind('jsonapi_search_mixed');
        $this->app->get($this->base."/{contenttype}/search", [$this, 'jsonapi_search'])
                  ->bind('jsonapi_search');
        $this->app->get($this->base."/{contenttype}/{slug}/{relatedContenttype}", [$this, 'jsonapi'])
                  ->value('relatedContenttype', null)
                  ->assert('slug', '[a-zA-Z0-9_\-]+')
                  ->bind('jsonapi');
        $this->app->get($this->base."/{contenttype}", [$this, 'jsonapi_list'])
                  ->bind('jsonapi_list');
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
        $this->request = $request;
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

    /**
     * Bolt uses `page` and `limit` instead of `page[number]` and `page[size]`
     * respectively. Currently, Bolt breaks if the `page` request parameter is
     * an array.
     *
     * A function that is going to handle pagination needs to call this function
     * before the Bolt's Storage::getContent() is called.
     *
     * @todo Bolt's Storage needs fixes.
     */
    private function fixBoltStorageRequest()
    {
        $originalParameters = $this->app['request']->query->all();

        // Write `page[size]` to `limit`.
        if (isset($originalParameters['page']['size'])) {
            $originalParameters['limit'] = $originalParameters['page']['size'];
        }

        // Write `page[number]` to `page`.
        if (isset($originalParameters['page']['number'])) {
            $originalParameters['page'] = $originalParameters['page']['number'];
        } else {
            unset($originalParameters['page']);
        }

        $this->app['request']->query->replace($originalParameters);
    }

    /**
     * Rewrites Bolt's `page` and `limit` back into `page[number]` and
     * `page[size]`. This function is useful for retaining the correct
     * parameters.
     *
     * @todo Bolt's Storage needs fixes.
     *
     * @param array $queryParameters A key,value-array with query parameters.
     *                               Usually obtained from something like
     *                               $request->query->all().
     * @return array Same as $queryParameters, but with page and limit variables
     *               rewritten to page[number] and page[size] respectively.
     */
    private function unfixBoltStorageRequest($queryParameters)
    {
        // Rewrite `page` back to `page['number']`.
        if (isset($queryParameters['page'])) {
            $page = $queryParameters['page'];
            $queryParameters['page'] = [];
            $queryParameters['page']['number'] = $page;
        }

        // Rewrite `limit` back to `page[size]`.
        if (isset($queryParameters['limit'])) {
            if (!isset($queryParameters['page']) || !is_array($queryParameters['page'])) {
                $queryParameters['page'] = [];
            }
            $queryParameters['page']['size'] = $queryParameters['limit'];
            unset($queryParameters['limit']);
        }

        return $queryParameters;
    }

    /**
     * Globally fetch all related content.
     *
     * @param string $contenttype The name of the contenttype.
     * @param \Bolt\Content[] $items
     * @return \Bolt\Content[]
     */
    private function fetchIncludedContent($contenttype, $items)
    {
        $include = $this->getContenttypesToInclude();
        $related = [];
        $tofetch = [];

        // Collect all ids per contenttype and then fetch em.
        foreach($include as $ct) {
            // Check if the include exists in the contenttypes definition.
            $exists = $this->app['config']->get("contenttypes/$contenttype/relations/$ct", false);
            if ($exists !== false) {
                $tofetch[$ct] = [];

                foreach ($items as $item) {
                    if ($item->relation && isset($item->relation[$ct])) {
                        $tofetch[$ct] = array_merge($tofetch[$ct], $item->relation[$ct]);
                    }
                }
            }
        }

        // ... and fetch!
        foreach ($tofetch as $ct => $ids) {
            $ids = implode(' || ', $ids);
            $pager = [];
            $items = $this->app['storage']->getContent($ct, [ 'paging' => false ], $pager, [ 'id' => $ids ]);
            $related = array_merge($related, $items);
        }

        // return array_values($related);

        $included = [];

        foreach(array_values($related) as $key => $item) {
            // todo: optimize dynamically!
            $ct = $item->contenttype['slug'];
            $ctAllFields = $this->getAllFieldNames($ct);
            $ctFields = $this->getFields($ct, $ctAllFields, 'list-fields');
            $included[$key] = $this->cleanItem($item, $ctFields);
        }

        return $included;
    }

    /**
     * Handles the include request parameter. Only contenttypes defined in the
     * configuration is allowed.
     *
     * @return string[] A list of names of contenttypes to include.
     */
    private function getContenttypesToInclude()
    {
        $include = [];

        if ($requestedContenttypes = $this->request->get('include')) {
            // These are the related contenttypes to include.
            $requestedContenttypes = explode(',', $requestedContenttypes);
            foreach($requestedContenttypes as $ct) {
                if (isset($this->config['contenttypes'][$ct])) {
                    $include[] = $ct;
                } else {
                    throw new \Exception("Contenttype with name [$ct] requested in include not found.");
                }
            }
        }

        return $include;
    }

    /**
     * Returns all field names for the given contenttype.
     *
     * @param string $contenttype The name of the contenttype.
     * @return string[] An array with all field definitions for the given
     *                  contenttype. This includes the base columns as well.
     */
    private function getAllFieldNames($contenttype)
    {
        $baseFields = \Bolt\Content::getBaseColumns();
        $definedFields = $this->app['config']->get("contenttypes/$contenttype/fields", []);
        $taxonomyFields = $this->getAllTaxonomies($contenttype);

        // Fields could be empty, although it's a rare case.
        if (!empty($definedFields)) {
            $definedFields = array_keys($definedFields);
        }

        $definedFields = array_merge($definedFields, $taxonomyFields);

        return array_merge($baseFields, $definedFields);
    }

    /**
     * Returns all taxonomy names for the given contenttype.
     *
     * @param string $contenttype The name of the contenttype.
     * @return string[] An array with all taxonomy names for the given
     *                  contenttype.
     */
    private function getAllTaxonomies($contenttype)
    {
        $taxonomyFields = $this->app['config']->get("contenttypes/$contenttype/taxonomy", []);
        return $taxonomyFields;
    }

    /**
     * Returns an array with the field names to be shown in the JSON response.
     *
     * @param string $contenttype The name of the contenttype.
     * @param array $allFields An array with all existing fields of the given
     *                         contenttype. This functions as an allowed fields
     *                         list if there is none defined.
     * @param string $defaultFieldsKey A string that is either 'list-fields' or
     *                                 'item-fields' that defines the default
     *                                 fallback fields in the config.
     * @return string[] An array with field names to be shown. It is possible that
     *                  this function returns an empty array.
     */
    private function getFields($contenttype, $allFields = [], $defaultFieldsKey = 'list-fields')
    {
        $fields = [];

        if (isset($this->config['contenttypes'][$contenttype]['allowed-fields'])) {
            $allowedFields = $this->config['contenttypes'][$contenttype]['allowed-fields'];
        } else {
            $allowedFields = $allFields;
        }

        // Check if there are any fields requested.
        if ($requestFields = $this->request->get('fields')) {
            if (isset($requestFields[$contenttype])) {
                $values = explode(',', $requestFields[$contenttype]);
                foreach ($values as $v) {
                    if (in_array($v, $allowedFields)) {
                        $fields[] = $v;
                    }
                }
            }
        }

        // Default on the default/fallback fields defined in the config.
        if (empty($fields)) {
            if (isset($this->config['contenttypes'][$contenttype][$defaultFieldsKey])) {
                $fields = $this->config['contenttypes'][$contenttype][$defaultFieldsKey];
                // todo: do we need to filter these through 'allowed-fields'?
            }
        }

        return $fields;
    }

    /**
     * Returns a suitable format for a given $item, where only the given $fields
     * (i.e 'attributes') are shown. If no $fields are defined, all the fields
     * defined in `contenttypes.yml` are used instead. This means that base
     * columns (set by Bolt), such as `datepublished`, are not shown.
     *
     * @param \Bolt\Content $item The item to be projected.
     * @param string[] $fields A list of fieldnames to be shown in the eventual
     *                         response. This may be empty, but will always
     *                         default on defined fields in `contenttypes.yml`.
     * @return mixed[] An array with data with $fields under 'attributes'.
     *                 Suitable for json encoding.
     *
     * @see Extension::getFields()
     */
    private function cleanItem($item, $fields = [])
    {
        $contenttype = $item->contenttype['slug'];

        if (empty($fields)) {
            $fields = array_keys($item->contenttype['fields']);
            if (!empty($item->taxonomy)) {
                $fields = array_merge($fields, array_keys($item->taxonomy));
            }
        }

        // Both 'id' and 'type' are always required. So remove them from $fields.
        // The remaining $fields go into 'attributes'.
        if(($key = array_search('id', $fields)) !== false) {
            unset($fields[$key]);
        }

        $id = $item->values['id'];
        $values = [
            'id' => $id,
            'type' => $contenttype,
        ];
        $attributes = [];
        $fields = array_unique($fields);

        foreach ($fields as $key => $field) {

            if (isset($item->values[$field])) {
                $attributes[$field] = $item->values[$field];
            }

            if (isset($item->taxonomy[$field])) {
                if (!isset($attributes['taxonomy'])) {
                    $attributes['taxonomy'] = [];
                }
                // Perhaps, do something interesting with these values in the future...
                // $taxonomy = $this->app['config']->get("taxonomy/$field");
                // $multiple = $this->app['config']->get("taxonomy/$field/multiple");
                // $behavesLike = $this->app['config']->get("taxonomy/$field/behaves_like");
                $attributes['taxonomy'][$field] = $item->taxonomy[$field];
            }

            if (in_array($field, ['datepublish', 'datecreated', 'datechanged', 'datedepublish']) && $this->config['date-iso-8601']) {
                $attributes[$field] = $this->dateISO($attributes[$field]);
            }

        }

        // Check if we have image or file fields present. If so, see if we need
        // to use the full URL's for these.
        foreach($item->contenttype['fields'] as $key => $field) {

            if ($field['type'] == 'imagelist' && !empty($attributes[$key])) {
                foreach ($attributes[$key] as &$value) {
                    $value['url'] = $this->makeAbsolutePathToImage($value['filename']);

                    if (is_array($this->config['thumbnail'])) {
                        $value['thumbnail'] = $this->makeAbsolutePathToThumbnail($value['filename']);
                    }
                }
            }

            if (($field['type'] == 'image' || $field['type'] == 'file') && isset($attributes[$key]) && isset($attributes[$key]['file'])) {
                $attributes[$key]['url'] = $this->makeAbsolutePathToImage($attributes[$key]['file']);
            }
            if ($field['type'] == 'image' && !empty($attributes[$key]) && is_array($this->config['thumbnail'])) {

                // Take 'old-school' image field into account, that are plain strings.
                if (!is_array($attributes[$key])) {
                    $attributes[$key] = array(
                        'file' => $attributes[$key]
                    );
                }

                $attributes[$key]['thumbnail'] = $this->makeAbsolutePathToThumbnail($attributes[$key]['file']);
            }

            if (in_array($field['type'], array('date', 'datetime')) && $this->config['date-iso-8601'] && !empty($attributes[$key])) {
                $attributes[$key] = $this->dateISO($attributes[$key]);
            }

        }

        if (!empty($attributes)) {
            $values['attributes'] = $attributes;
        }

        $values['links'] = [
            'self' => sprintf('%s/%s/%s', $this->basePath, $contenttype, $id),
        ];

        // todo: Handle taxonomies
        //       1. tags
        //       2. categories
        //       3. groupings
        if ($item->taxonomy) {
            foreach($item->taxonomy as $key => $value) {
                // $values['attributes']['taxonomy'] = [];
            }
        }

        // todo: Since Bolt relationships are a bit _different_ than the ones in
        //       relational databases, I am not sure if we need to do an
        //       additional check for `multiple` = true|false in the definitions
        //       in `contenttypes.yml`.
        //
        // todo: Depending on multiple, empty relationships need a null or [],
        //       if they don't exist.
        if ($item->relation) {
            $relationships = [];
            foreach ($item->relation as $ct => $ids) {
                $data = [];
                foreach($ids as $i) {
                    $data[] = [
                        'type' => $ct,
                        'id' => $i
                    ];
                }

                $relationships[$ct] = [
                    'links' => [
                        // 'self' -- this is irrelevant for now
                        'related' => "$this->basePath/$contenttype/$id/$ct"
                    ],
                    'data' => $data
                ];
            }
            $values['relationships'] = $relationships;
        }

        return $values;
    }

    /**
     * Returns the values for the "links" object in a listing response.
     *
     * @param string $contenttype The name of the contenttype.
     * @param int $currentPage The current page number.
     * @param int $totalPages The total number of pages.
     * @param int $pageSize The number of items per page.
     * @return mixed[] An array with URLs for the current page and related
     *                 pagination pages.
     */
    private function makeLinks($contenttype, $currentPage, $totalPages, $pageSize)
    {
        $basePath = $this->basePath;
        $basePathContenttype = $basePath . '/' . $contenttype;
        $prevPage = max($currentPage - 1, 1);
        $nextPage = min($currentPage + 1, $totalPages);
        $firstPage = 1;
        $pagination = $firstPage != $totalPages;

        $links = [];
        $querystring = '';
        $defaultQuerystring = $this->makeQueryParameters();

        $params = $pagination ? $this->makeQueryParameters([$this->paginationNumberKey => $currentPage]) : $defaultQuerystring;
        $links["self"] = $basePathContenttype.$params;

        // The following links only exists if a query was made using pagination.
        if ($currentPage != $firstPage) {
            $params = $this->makeQueryParameters([$this->paginationNumberKey => $firstPage]);
            $links["first"] = $basePathContenttype.$params;
        }
        if ($currentPage != $totalPages) {
            $params = $this->makeQueryParameters([$this->paginationNumberKey => $totalPages]);
            $links["last"] = $basePathContenttype.$params;
        }
        if ($currentPage != $prevPage) {
            $params = $this->makeQueryParameters([$this->paginationNumberKey => $prevPage]);
            $links["prev"] = $basePathContenttype.$params;
        }
        if ($currentPage != $nextPage) {
            $params = $this->makeQueryParameters([$this->paginationNumberKey => $nextPage]);
            $links["next"] = $basePathContenttype.$params;
        }

        return $links;
    }

    /**
     * Make related links for a singular item.
     *
     * @param \Bolt\Content $item
     * @return mixed[] An array with URLs for the relationships.
     */
    private function makeRelatedLinks($item)
    {
        $related = [];
        $contenttype = $item->contenttype['slug'];
        $id = $item->values['id'];

        if ($item->relation) {
            foreach ($item->relation as $ct => $ids) {
                $related[$ct] = [
                    'href' => "$this->basePath/$contenttype/$id/$ct",
                    'meta' => [
                        'count' => count($ids)
                    ]
                ];
            }
        }

        return $related;
    }

    /**
     * Make a new querystring while preserving current query parameters with the
     * option to override values.
     *
     * @param array $overrides A (key,value)-array with elements to override in
     *                         the current query string.
     * @param bool $buildQuery Returns a querystring if set to true, otherwise
     *                          returns the array with (key,value)-pairs.
     * @return mixed query parameters in either array or string form.
     *
     * @see \Bolt\Helpers\Arr::mergeRecursiveDistinct()
     */
    private function makeQueryParameters($overrides = [], $buildQuery = true)
    {
        $queryParameters = $this->request->query->all();

        // todo: (optional) cleanup. There is a default set of fields we can
        //       expect using this Extension and jsonapi. Or we could ignore
        //       them like we already do.

        // Using Bolt's Helper Arr class for merging and overriding values.
        $queryParameters = Arr::mergeRecursiveDistinct($queryParameters, $overrides);

        $queryParameters = $this->unfixBoltStorageRequest($queryParameters);

        if ($buildQuery) {
            // No need to urlencode these, afaik.
            $querystring =  urldecode(http_build_query($queryParameters));
            if (!empty($querystring)) {
                $querystring = '?' . $querystring;
            }
            return $querystring;
        }
        return $queryParameters;
    }

    // -------------------------------------------------------------------------
    // -- RESPONSES                                                           --
    // -------------------------------------------------------------------------

    /**
     * Respond with a 404 Not Found.
     *
     * @param array $data Optional data to pass on through the response.
     * @return Symfony\Component\HttpFoundation\Response
     */
    private function responseNotFound($data = [])
    {
        return $this->responseError('404', 'Not Found', $data);
    }

    /**
     * Respond with a simple 400 Invalid Request.
     *
     * @param array $data Optional data to pass on through the response.
     * @return Symfony\Component\HttpFoundation\Response
     */
    private function responseInvalidRequest($data = [])
    {
        return $this->responseError('400', 'Invalid Request', $data);
    }

    /**
     * Make a response with an error.
     *
     * @param string $status HTTP status code.
     * @param string $title Human-readable summary of the problem.
     * @param array $data Optional data to pass on through the response.
     * @return Symfony\Component\HttpFoundation\Response
     */
    private function responseError($status, $title, $data = [])
    {
        // todo: (optional) filter unnecessary fields.
        // $allowedErrorFields = [ 'id', 'links', 'about', 'status', 'code', 'title', 'detail', 'source', 'meta' ];

        $data['status'] = $status;
        $data['title'] = $title;

        return $this->response([
            'errors' => $data
        ]);
    }

    /**
     * Makes a JSON response, with either data or an error, never both.
     *
     * @param array $array The data to wrap in the response.
     * @return Symfony\Component\HttpFoundation\Response
     */
    private function response($array)
    {
        $json_encodeOptions = isset($this->config['jsonoptions']) ? $this->config['jsonoptions'] : JSON_PRETTY_PRINT;
        $json = json_encode($array, $json_encodeOptions);

        if (isset($array['errors'])) {
            $status = isset($array['errors']['status']) ? $array['errors']['status'] : 400;
            $response = new Response($json, $status);
        } else {
            $response = new Response($json, 201);
        }

        if (!empty($this->config['headers']) && is_array($this->config['headers'])) {
            foreach ($this->config['headers'] as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        if ($callback = $this->request->get('callback')) {
            $response->setCallback($callback);
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // -- UTILITY FUNCTIONS                                                   --
    // -------------------------------------------------------------------------

    private function dateISO($date)
    {
        $dateObject = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
        return($dateObject->format('c'));
    }

    private function makeAbsolutePathToImage($filename = '')
    {
        return sprintf('%s%s%s',
            $this->app['paths']['canonical'],
            $this->app['paths']['files'],
            $filename
            );
    }

    private function makeAbsolutePathToThumbnail($filename = '')
    {
        return sprintf('%s/thumbs/%sx%s/%s',
            $this->app['paths']['canonical'],
            $this->config['thumbnail']['width'],
            $this->config['thumbnail']['height'],
            $filename
            );
    }

}
