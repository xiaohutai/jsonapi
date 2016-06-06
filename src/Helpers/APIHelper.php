<?php
namespace Bolt\Extension\Bolt\JsonApi\Helpers;

use Bolt\Content;
use Bolt\Helpers\Arr;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Silex\Application;

/**
 * Class APIHelper
 * @package JSONAPI\Helpers
 */
class APIHelper
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
     * @var UtilityHelper
     */
    private $utilityHelper;

    /**
     * APIHelper constructor.
     * @param Application $app
     * @param Config $config
     * @param UtilityHelper $utilityHelper
     */
    public function __construct(Application $app, Config $config, UtilityHelper $utilityHelper)
    {
        $this->app = $app;
        $this->config = $config;
        $this->utilityHelper = $utilityHelper;
    }

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
    public function fixBoltStorageRequest()
    {
        $originalParameters = $this->config->getCurrentRequest()->query->all();

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

        $this->config->getCurrentRequest()->query->replace($originalParameters);
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
    public function unfixBoltStorageRequest($queryParameters)
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
     * @param string $contentType The name of the contenttype.
     * @param \Bolt\Content[] $items
     * @return \Bolt\Content[]
     */
    public function fetchIncludedContent($contentType, $items)
    {
        $include = $this->getContenttypesToInclude();
        $related = [];
        $toFetch = [];

        // Collect all ids per contenttype and then fetch em.
        foreach($include as $ct) {
            // Check if the include exists in the contenttypes definition.
            $exists = $this->app['config']->get("contenttypes/$contentType/relations/$ct", false);
            if ($exists !== false) {
                $toFetch[$ct] = [];

                foreach ($items as $item) {
                    if ($item->relation && isset($item->relation[$ct])) {
                        $toFetch[$ct] = array_merge($toFetch[$ct], $item->relation[$ct]);
                    }
                }
            }
        }

        // ... and fetch!
        foreach ($toFetch as $ct => $ids) {
            $ids = implode(' || ', $ids);
            $pager = [];
            $items = $this->app['storage']->getContent($ct, [ 'paging' => false ], $pager, [ 'id' => $ids ]);
            if ($items instanceof Content) {
                $newItems[] = $items;
            } else {
                $newItems = $items;
            }
            $related = array_merge($related, $newItems);
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
     * @return \string[] A list of names of contenttypes to include.
     * @throws \Exception
     */
    public function getContenttypesToInclude()
    {
        $include = [];

        if ($requestedContentTypes = $this->config->getCurrentRequest()->get('include')) {
            $requestedContentTypes = explode(',', $requestedContentTypes);
            foreach($requestedContentTypes as $ct) {
                if (array_key_exists($ct, $this->config->getContentTypes())) {
                    $include[] = $ct;
                } else {
                    throw new \Exception("Content type with name [$ct] requested in include not found.");
                }
            }
        }

        return $include;
    }


    /**
     * Returns all field names for the given contenttype.
     *
     * @param string $contentType The name of the contenttype.
     * @return string[] An array with all field definitions for the given
     *                  contenttype. This includes the base columns as well.
     */
    public function getAllFieldNames($contentType)
    {
        $baseFields = \Bolt\Content::getBaseColumns();
        $definedFields = $this->app['config']->get("contenttypes/$contentType/fields", []);
        $taxonomyFields = $this->getAllTaxonomies($contentType);

        // Fields could be empty, although it's a rare case.
        if (!empty($definedFields)) {
            $definedFields = array_keys($definedFields);
        }

        $definedFields = array_merge($definedFields, $taxonomyFields);

        return array_merge($baseFields, $definedFields);
    }

    /**
     * Returns all taxonomy names for the given content type.
     *
     * @param string $contentType The name of the content type.
     * @return string[] An array with all taxonomy names for the given
     *                  contenttype.
     */
    public function getAllTaxonomies($contentType)
    {
        $taxonomyFields = $this->app['config']->get("contenttypes/$contentType/taxonomy", []);
        return $taxonomyFields;
    }

    /**
     * Returns an array with the field names to be shown in the JSON response.
     *
     * @param string $contentType The name of the contenttype.
     * @param array $allFields An array with all existing fields of the given
     *                         contenttype. This functions as an allowed fields
     *                         list if there is none defined.
     * @param string $defaultFieldsKey A string that is either 'list-fields' or
     *                                 'item-fields' that defines the default
     *                                 fallback fields in the config.
     * @return string[] An array with field names to be shown. It is possible that
     *                  this function returns an empty array.
     */
    public function getFields($contentType, $allFields = [], $defaultFieldsKey = 'list-fields')
    {
        $fields = [];
        $contentTypes = $this->config->getContentTypes();

        if (isset($contentTypes[$contentType]['allowed-fields'])) {
            $allowedFields = $contentTypes[$contentType]['allowed-fields'];
        } else {
            $allowedFields = $allFields;
        }

        // Check if there are any fields requested.
        if ($requestFields = $this->config->getCurrentRequest()->get('fields')) {
            if (isset($requestFields[$contentType])) {
                $values = explode(',', $requestFields[$contentType]);
                foreach ($values as $v) {
                    if (in_array($v, $allowedFields)) {
                        $fields[] = $v;
                    }
                }
            }
        }

        // Default on the default/fallback fields defined in the config.
        if (empty($fields)) {
            if (isset($this->config->getContentTypes()[$contentType][$defaultFieldsKey])) {
                $fields = $this->config->getContentTypes()[$contentType][$defaultFieldsKey];
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
    public function cleanItem($item, $fields = [])
    {
        $contentType = $item->contenttype['slug'];

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
            'type' => $contentType,
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

            if (in_array($field, ['datepublish', 'datecreated', 'datechanged', 'datedepublish']) && $this->config->getDateIso()) {
                $attributes[$field] = $this->utilityHelper->dateISO($attributes[$field]);
            }

        }

        // Check if we have image or file fields present. If so, see if we need
        // to use the full URL's for these.
        foreach($item->contenttype['fields'] as $key => $field) {

            if ($field['type'] == 'imagelist' && !empty($attributes[$key])) {
                foreach ($attributes[$key] as &$image) {
                    $image['url'] = $this->utilityHelper->makeAbsolutePathToImage($image['filename']);

                    if (is_array($this->config->getThumbnail())) {
                        $image['thumbnail'] = $this->utilityHelper->makeAbsolutePathToThumbnail($image['filename']);
                    }
                }
            }

            if (($field['type'] == 'image' || $field['type'] == 'file') && isset($attributes[$key]) && isset($attributes[$key]['file'])) {
                $attributes[$key]['url'] = $this->utilityHelper->makeAbsolutePathToImage($attributes[$key]['file']);
            }
            if ($field['type'] == 'image' && !empty($attributes[$key]) && is_array($this->config->getThumbnail())) {

                // Take 'old-school' image field into account, that are plain strings.
                if (!is_array($attributes[$key])) {
                    $attributes[$key] = array(
                        'file' => $attributes[$key]
                    );
                }

                $attributes[$key]['thumbnail'] = $this->utilityHelper->makeAbsolutePathToThumbnail($attributes[$key]['file']);
            }

            if (in_array($field['type'], array('date', 'datetime')) && $this->config->getDateIso() && !empty($attributes[$key])) {
                $attributes[$key] = $this->utilityHelper->dateISO($attributes[$key]);
            }

        }

        if (!empty($attributes)) {

            // Recursively walk the array..
            array_walk_recursive($attributes, function(&$item) {
                // Make sure that any \Twig_Markup objects are cast to plain strings.
                if ($item instanceof \Twig_Markup) {
                    $item = $item->__toString();
                }

                // Handle replacements.
                if (!empty($this->config->getReplacements())) {
                    foreach ($this->config->getReplacements() as $from => $to) {
                        $item = str_replace($from, $to, $item);
                    }
                }

            });

            $values['attributes'] = $attributes;
        }

        $values['links'] = [
            'self' => sprintf('%s/%s/%s', $this->config->getBasePath(), $contentType, $id),
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
                        'related' => $this->config->getBasePath()."/$contentType/$id/$ct"
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
     * @param string $contentType The name of the contenttype.
     * @param int $currentPage The current page number.
     * @param int $totalPages The total number of pages.
     * @param int $pageSize The number of items per page.
     * @return mixed[] An array with URLs for the current page and related
     *                 pagination pages.
     */
    public function makeLinks($contentType, $currentPage, $totalPages, $pageSize)
    {
        $basePath = $this->config->getBasePath();
        $basePathContentType = $basePath . '/' . $contentType;
        $prevPage = max($currentPage - 1, 1);
        $nextPage = min($currentPage + 1, $totalPages);
        $firstPage = 1;
        $pagination = $firstPage != $totalPages;

        $links = [];
        $defaultQueryString = $this->makeQueryParameters();

        $params = $pagination ? $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $currentPage]) : $defaultQueryString;
        $links["self"] = $basePathContentType.$params;

        // The following links only exists if a query was made using pagination.
        if ($currentPage != $firstPage) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $firstPage]);
            $links["first"] = $basePathContentType.$params;
        }
        if ($currentPage != $totalPages) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $totalPages]);
            $links["last"] = $basePathContentType.$params;
        }
        if ($currentPage != $prevPage) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $prevPage]);
            $links["prev"] = $basePathContentType.$params;
        }
        if ($currentPage != $nextPage) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $nextPage]);
            $links["next"] = $basePathContentType.$params;
        }

        return $links;
    }

    /**
     * Make related links for a singular item.
     *
     * @param \Bolt\Content $item
     * @return mixed[] An array with URLs for the relationships.
     */
    public function makeRelatedLinks($item)
    {
        $related = [];
        $contentType = $item->contenttype['slug'];
        $id = $item->values['id'];

        if ($item->relation) {
            foreach ($item->relation as $ct => $ids) {
                $related[$ct] = [
                    'href' => $this->config->getBasePath()."/$contentType/$id/$ct",
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
    public function makeQueryParameters($overrides = [], $buildQuery = true)
    {
        $queryParameters = $this->config->getCurrentRequest()->query->all();

        // todo: (optional) cleanup. There is a default set of fields we can
        //       expect using this Extension and jsonapi. Or we could ignore
        //       them like we already do.

        // Using Bolt's Helper Arr class for merging and overriding values.
        $queryParameters = Arr::mergeRecursiveDistinct($queryParameters, $overrides);

        $queryParameters = $this->unfixBoltStorageRequest($queryParameters);

        if ($buildQuery) {
            // No need to urlencode these, afaik.
            $queryString =  urldecode(http_build_query($queryParameters));
            if (!empty($queryString)) {
                $queryString = '?' . $queryString;
            }
            return $queryString;
        }
        return $queryParameters;
    }
}