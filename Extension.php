<?php
/**
 * JSONAPI extension for Bolt. Forked from the JSONAccess extension.
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
 *
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 *
 * ---
 * [Pagination]
 * Bolt uses the page-based pagination strategy.
 *
 * Recommended pagination strategies according to jsonapi are:
 * - page-based   : page[number] and page[size]
 * - offset-based : page[offset] and page[limit]
 * - cursor-based : page[cursor]
 *
 * source: http://jsonapi.org/format/#fetching-pagination
 *
 * Note: Since Bolt breaks when using page[number] and page[size],
 * we're currently using $page and $limit respectively.
 *
 * ---
 * [todo]
 * This extension is a work in progress. Simple features are available. However,
 * the following features are not implemented yet:
 * - include / relationships.
 * - handling / taxonomies.
 * - sorting
 * - handling json fields -> json decode these values?
 * - handling select contenttype fields -> handle them as has-one relationships?
 * - search.
 * - i18n for error 'detail' messages.
 *
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
        return "JSONAPI";
    }

    //
    // Examples:
    //
    // Basic:
    //     /{contenttype}
    //     /{contenttype}/{id}
    //     /{contenttype}?page={x}&limit={y}
    //     /{contenttype}?include={relationship1,relationship2}
    //     /{contenttype}?fields[{contenttype1}]={field1,field2} -- Note: taxonomies and relationships are fields as well.
    //
    // Relationships:
    //     /{contenttype}/{id}/relationships/{relationship} -- Note: this "relationship" is useful for handling the relationship between two instances.
    //     /{contenttype}/{id}/{relationship} -- Note: this "related resource" is useful for fetching the related data. These are not "self" links.
    //
    // Filters:
    //     /{contenttype}?filter[{contenttype1}]={value1,value2}
    //     /{contenttype}?filter[{field1}]={value1,value2}&filter[{field2}]={value3,value4} -- For Bolt, this seems the most logical (similar to a `where` clause)
    //
    // Search:
    //     /{contenttype}?q={query} -- search within a contenttype
    //     /search/q={query} -- search in all contenttypes
    //
    // sources: http://jsonapi.org/examples/
    //          http://jsonapi.org/recommendations/
    //
    public function initialize()
    {
        if(isset($this->config['base'])) {
            $this->base = $this->config['base'];
        }
        $this->basePath = $this->app['paths']['canonical'] . $this->base;

        $this->app->get($this->base."/search", [$this, 'jsonapi_search']) // todo: "jsonapi_search_mixed"
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

    public function jsonapi_list(Request $request, $contenttype)
    {
        $this->request = $request;

        if (!array_key_exists($contenttype, $this->config['contenttypes'])) {
            return $this->responseNotFound([
                'detail' => "Contenttype with name [$contenttype] not found."
            ]);
        }
        $options = [];
        // if ($limit = $request->get('page')['size']) { // todo: breaks things in src/Storage.php at executeGetContentQueries
        if ($limit = $request->get($this->paginationSizeKey)) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }
        // if ($page = $request->get('page')['number']) { // todo: breaks things in src/Storage.php at executeGetContentQueries
        if ($page = $request->get($this->paginationNumberKey)) {
            $page = intval($page);
            if ($page >= 1) {
                $options['page'] = $page;
            }
        }
        if ($order = $request->get('sort')) {
            // todo: comma-separated list with a minus prefix for descending
            // if (!preg_match('/^([a-zA-Z][a-zA-Z0-9_\\-]*)\\s*(ASC|DESC)?$/', $order, $matches)) {
            //     return $this->responseInvalidRequest([
            //         'detail' => "The sort parameter is incorrect: [$order]."
            //     ]);
            // }
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

        $items = $this->app['storage']->getContent($contenttype, $options, $pager, $where);

        // If we don't have any items, this can mean one of two things: either
        // the content type does not exist (in which case we'll get a non-array
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

        // -- included
        // Handle "include" and fetch related relationships in current query.
        try {
            $included = $this->fetchIncludedContent($contenttype, $items);
        } catch(\Exception $e) {
            return $this->responseInvalidRequest([
                'detail' => $e->getMessage()
            ]);
        }

        $included = array_values($included);
        foreach($included as $key => $item) {
            // todo: optimize dynamically!
            $ct = $item->contenttype['slug'];
            $ctAllFields = $this->getAllFieldNames($ct);
            $ctFields = $this->getFields($ct, $ctAllFields, 'list-fields');

            $included[$key] = $this->cleanItem($item, $ctFields);
        }
        // -- /included

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

            if ($prev)  {
                $links['prev'] = sprintf('%s/%s/%d%s', $this->basePath, $contenttype, $prev->values['id'], $defaultQuerystring);
            }
            if ($next) {
                $links['next'] = sprintf('%s/%s/%d%s', $this->basePath, $contenttype, $next->values['id'], $defaultQuerystring);
            }

            $response = $this->response([
                'links' => $links,
                'data' => $values,
            ]);
        }

        return $response;
    }

    /**
     * @todo: Handle search, because it's going to be useful (e.g. ajax search).
     *
     * @param Request $request
     * @param string $contenttype The name of the specific $contenttype to search
     *                             in, otherwise search all contenttypes.
     */
    public function jsonapi_search(Request $request, $contenttype = null)
    {
        $this->request = $request;

        if ($contenttype !== null) {
            // search in $contenttype.
        } else {
            // search all searchable contenttypes.
        }

        // $this->app['storage']
        // make a filter query

        return $this->responseInvalidRequest([
            'detail' => "This feature is not yet implemented."
        ]);
    }

    // -------------------------------------------------------------------------
    // -- HELPER FUNCTIONS                                                    --
    // -------------------------------------------------------------------------

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

        return $related;
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
        $definedFields = array_keys($this->app['config']->get("contenttypes/$contenttype/fields"));
        return array_merge($baseFields, $definedFields);
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
            $attributes[$field] = $item->values[$field];
        }

        // Check if we have image or file fields present. If so, see if we need
        // to use the full URL's for these.
        foreach($item->contenttype['fields'] as $key => $field) {
            if (($field['type'] == 'image' || $field['type'] == 'file') && isset($attributes[$key])) {
                $attributes[$key]['url'] = sprintf('%s%s%s',
                    $this->app['paths']['canonical'],
                    $this->app['paths']['files'],
                    $attributes[$key]['file']
                    );
            }
            if ($field['type'] == 'image' && isset($attributes[$key]) && is_array($this->config['thumbnail'])) {
                $attributes[$key]['thumbnail'] = sprintf('%s/thumbs/%sx%s/%s',
                    $this->app['paths']['canonical'],
                    $this->config['thumbnail']['width'],
                    $this->config['thumbnail']['height'],
                    $attributes[$key]['file']
                    );
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
        return $this->response($data);
    }

    /**
     * Makes a JSON response, with either data or an error, never both.
     *
     * @param array $array The data to wrap in the response.
     * @return Symfony\Component\HttpFoundation\Response
     */
    private function response($array)
    {
        $json = json_encode($array, JSON_PRETTY_PRINT);
        // $json = json_encode($array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);

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

}
