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
 * This extension is a work in progress. Simple features are available
 * - include / relationships
 * - handling / taxonomies
 * - handling json fields
 * - handling select contenttype fields
 * - preserve all request params
 * - search
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
            return $this->responseNotFound();
        }
        $options = [];
        // if ($limit = $request->get('page')['size']) { // breaks things in src/Storage.php at executeGetContentQueries
        if ($limit = $request->get($this->paginationSizeKey)) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }
        // if ($page = $request->get('page')['number']) { // breaks things in src/Storage.php at executeGetContentQueries
        if ($page = $request->get($this->paginationNumberKey)) {
            $page = intval($page);
            if ($page >= 1) {
                $options['page'] = $page;
            }
        }
        if ($order = $request->get('order')) {
            if (!preg_match('/^([a-zA-Z][a-zA-Z0-9_\\-]*)\\s*(ASC|DESC)?$/', $order, $matches)) {
                $this->responseInvalidRequest();
            }
            $options['order'] = $order;
        }

        // Enable pagination
        $options['paging'] = true;
        $pager  = [];
        $where  = [];

        $allFields = $this->getAllFieldNames($contenttype);
        $fields = $this->getFields($contenttype, $allFields, 'list-fields');

        // todo: handle "include" / relationships
        // $included = [];
        // if ($include = $request->get('include')) {
        //     $where = [];
        // }

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config['contenttypes'][$contenttype]['where-clause'])) {
            $where = $this->config['contenttypes'][$contenttype]['where-clause'];
        }

        // Handle $filter[], this modifies the $where[] clause.
        if ($filters = $request->get('filter')) {
            foreach($filters as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return $this->responseInvalidRequest();
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
            $this->responseInvalidRequest([
                'detail' => "Configuration error: $contenttype is configured as a JSON end-point, but doesn't exist as a content type."
            ]);
        }

        if (empty($items)) {
            $items = [];
        }

        // todo: relationships
        $items = array_values($items);
        foreach($items as $key => $item) {
            $items[$key] = $this->cleanItem($item, $fields);
        }

        return $this->response([
            'links' => $this->makeLinks($contenttype, $pager['current'], intval($pager['totalpages']), $limit),
            'meta' => [
                "count" => count($items),
                "total" => intval($pager['count'])
            ],
            'data' => $items,
            // 'related' => [],
            // 'included' => $included // included related objects
        ]);
    }

    public function jsonapi(Request $request, $contenttype, $slug, $relatedContenttype)
    {
        $this->request = $request;

        if (!array_key_exists($contenttype, $this->config['contenttypes'])) {
            return $this->responseNotFound();
        }

        $item = $this->app['storage']->getContent("$contenttype/$slug");
        if (!$item) {
            return $this->responseNotFound();
        }

        // If a related entity name is given, we fetch its content instead
        if ($relatedContenttype !== null)
        {
            $items = $item->related($relatedContenttype);
            if (!$items) {
                return $this->responseNotFound();
            }
            // $items = array_map([$this, 'clean_list_item'], $items); // REFACTOR!
            $response = $this->response([
                'data' => $items
            ]);

        } else {

            $allFields = $this->getAllFieldNames($contenttype);
            $fields = $this->getFields($contenttype, $allFields, 'item-fields');
            $values = $this->cleanItem($item, $fields);
            $prev = $item->previous();
            $next = $item->next();

            $links = [
                'self' => $values['links']['self'],
            ];
            if ($prev)  {
                $links['prev'] = sprintf('%s/%s/%d', $this->basePath, $contenttype, $prev->values['id']);
            }
            if ($next) {
                $links['next'] = sprintf('%s/%s/%d', $this->basePath, $contenttype, $next->values['id']);
            }

            $response = $this->response([
                'links' => $links,
                'data' => $values,
            ]);
        }

        return $response;
    }

    // todo: handle search
    public function jsonapi_search(Request $request, $contenttype = null)
    {
        if ($contenttype !== null) {
            // search in $contenttype
        } else {
            // search all contenttypes
        }

        $this->request = $request;
        // $this->app['storage']
        // make a filter query
        return $this->responseNotFound();
    }

    // -------------------------------------------------------------------------
    // -- HELPER FUNCTIONS                                                    --
    // -------------------------------------------------------------------------

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

        $values = [
            'id' => $item->values['id'],
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

        // todo: add "links"
        $values['links'] = [
            'self' => sprintf('%s/%s/%s', $this->basePath, $contenttype, $item->values['id']),
        ];

        // todo: taxonomy
        // todo: tags
        // todo: categories
        // todo: groupings
        if ($item->taxonomy) {
            foreach($item->taxonomy as $key => $value) {
                // $values['attributes']['taxonomy'] = [];
            }
        }

        // todo: "relationships"
        if ($item->relation) {
            $values['relationships'] = [];
        }

        // todo: "meta"
        // todo: "links"

        return $values;
    }

    /**
     * Returns the values for the "links" object in a listing response.
     *
     * @param string $contenttype The name of the contenttype.
     * @param int $currentPage The current page number.
     * @param int $totalPages The total number of pages.
     * @param int $pageSize The number of items per page.
     * @return mixed[] An array with URLs for the current page and related pages.
     */
    private function makeLinks($contenttype, $currentPage, $totalPages, $pageSize)
    {
        $basePath = $this->basePath;
        $prevPage = max($currentPage - 1, 1);
        $nextPage = min($currentPage + 1, $totalPages);
        $firstPage = 1;
        $pagination = $firstPage != $totalPages;

        $links = [];
        $querystring = '';
        $defaultQuerystring = $this->makeQueryParameters();

        $params = $pagination ? $this->makeQueryParameters([$this->paginationNumberKey => $currentPage]) : $defaultQuerystring;
        $links["self"] = "$basePath/$contenttype?$params";
        if ($currentPage != $firstPage) {
            $params = $pagination ? $this->makeQueryParameters([$this->paginationNumberKey => $firstPage]) : $defaultQuerystring;
            $links["first"] = "$basePath/$contenttype?$params";
        }
        if ($currentPage != $totalPages) {
            $params = $pagination ? $this->makeQueryParameters([$this->paginationNumberKey => $totalPages]) : $defaultQuerystring;
            $links["last"] = "$basePath/$contenttype?$params";
        }
        if ($currentPage != $prevPage) {
            $params = $pagination ? $this->makeQueryParameters([$this->paginationNumberKey => $prevPage]) : $defaultQuerystring;
            $links["prev"] = "$basePath/$contenttype?$params";
        }
        if ($currentPage != $nextPage) {
            $params = $pagination ? $this->makeQueryParameters([$this->paginationNumberKey => $nextPage]) : $defaultQuerystring;
            $links["next"] = "$basePath/$contenttype?$params";
        }

        // todo: use "related" for additional related links.
        // $links["related"]

        return $links;
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
            return  urldecode(http_build_query($queryParameters));
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
        // todo: filter unnecessary fields.
        // $allowedErrorFields = [ 'id', 'links', 'about', 'status', 'code', 'title', 'detail', 'source', 'meta' ];
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
            // dump($this->config['headers']);
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
