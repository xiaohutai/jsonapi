<?php
namespace Bolt\Extension\Bolt\JsonApi\Helpers;

use Bolt\Content;
use Bolt\Helpers\Arr;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Storage\Collection\Relations;
use Bolt\Storage\Collection\Taxonomy;
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
     * Returns a suitable format for a given $item, where only the given $fields
     * (i.e 'attributes') are shown. If no $fields are defined, all the fields
     * defined in `contenttypes.yml` are used instead. This means that base
     * columns (set by Bolt), such as `datepublished`, are not shown.
     *
     * @param \Bolt\Storage\Entity\Content $item The item to be projected.
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
        $contentType = (string) $item->getContenttype();
        //$contentType = $item->contenttype['slug'];

        if (empty($fields)) {
            $fields = array_keys($item->_fields);
            /*if (!empty($item->getTaxonomy())) {
                $fields = array_merge($fields, array_keys($item->getTaxonomy()));
            }*/
        }

        // Both 'id' and 'type' are always required. So remove them from $fields.
        // The remaining $fields go into 'attributes'.
        if (($key = array_search('id', $fields)) !== false) {
            unset($fields[$key]);
        }

        $id = $item->getId();
        $values = [
            'id' => strval($id),
            'type' => $contentType,
        ];
        $attributes = [];
        $fields = array_unique($fields);

        foreach ($fields as $key => $field) {
            if ($item->get($field)) {
                //Exclude relationships
                if (!$item->get($field) instanceof Relations) {
                    $attributes[$field] = $item->get($field);
                }
            }

            if ($item->get($field) instanceof Taxonomy) {
                unset($attributes[$field]);
                if (! isset($attributes['taxonomy'])) {
                    $attributes['taxonomy'] = [];
                }

                foreach ($item->get($field) as $field) {
                    $type = $field->getTaxonomytype();
                    $slug = $field->getSlug();
                    $route = '/' . $type . '/' . $slug;
                    $attributes['taxonomy'][$type][$route] = $field->getName();
                }
            }

            /*if ($item->getTaxonomy()->get($field)) {
                $test = $item->getTaxonomy($field);
                if (!isset($attributes['taxonomy'])) {
                    $attributes['taxonomy'] = [];
                }
                // Perhaps, do something interesting with these values in the future...
                // $taxonomy = $this->app['config']->get("taxonomy/$field");
                // $multiple = $this->app['config']->get("taxonomy/$field/multiple");
                // $behavesLike = $this->app['config']->get("taxonomy/$field/behaves_like");
                $attributes['taxonomy'][$field] = $item->taxonomy[$field];
            }*/

            if (in_array($field, ['datepublish', 'datecreated', 'datechanged', 'datedepublish']) && $this->config->getDateIso()) {
                $attributes[$field] = $this->utilityHelper->dateISO($attributes[$field]);
            }

        }

        // Check if we have image or file fields present. If so, see if we need
        // to use the full URL's for these.
        foreach ($item->contenttype['fields'] as $key => $field) {
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
            array_walk_recursive ($attributes, function(&$item) {
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
        if ($item->getTaxonomy()) {
            foreach ($item->getTaxonomy() as $key => $value) {
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
        if (count($item->getRelation()) > 0) {
            $relationships = [];
            foreach ($item->getRelation() as $relatedType) {
                $data = [];
                $id = $relatedType->getFromId();
                $fromType = $relatedType->getFromContenttype();
                $toType = $relatedType->getToContenttype();

                $data[] = [
                    'type' => $toType,
                    'id' => $id
                ];

                /*foreach($ids as $i) {
                    $data[] = [
                        'type' => $ct,
                        'id' => $i
                    ];
                }*/

                $relationships[$toType] = [
                    'links' => [
                        // 'self' -- this is irrelevant for now
                        'related' => $this->config->getBasePath()."/$fromType/$id/$toType"
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
     * @param \Bolt\Storage\Entity\Content $item
     * @return mixed[] An array with URLs for the relationships.
     */
    public function makeRelatedLinks($item)
    {
        $related = [];
        $contentType = (string)$item->getContenttype();
        $id = $item->getId();

        if (count($item->getRelation()) > 0) {
            foreach ($item->getRelation() as $relatedType) {
                $id = $relatedType->getId();
                $fromType = $relatedType->getFromContenttype();
                $toType = $relatedType->getToContenttype();

                $related[$fromType] = [
                    'href' => $this->config->getBasePath()."/$contentType/$id/$fromType",
                    'meta' => [
                        'count' => 1
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
    public function     makeQueryParameters($overrides = [], $buildQuery = true)
    {
        $queryParameters = $this->config->getCurrentRequest()->query->all();

        // todo: (optional) cleanup. There is a default set of fields we can
        //       expect using this Extension and jsonapi. Or we could ignore
        //       them like we already do.

        // Using Bolt's Helper Arr class for merging and overriding values.
        $queryParameters = Arr::mergeRecursiveDistinct($queryParameters, $overrides);

        //$queryParameters = $this->unfixBoltStorageRequest($queryParameters);

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