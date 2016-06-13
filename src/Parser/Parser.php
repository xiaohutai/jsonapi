<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Helpers\UtilityHelper;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\FieldFactory;
use Bolt\Storage\Collection\Relations;
use Bolt\Storage\Collection\Taxonomy;

class Parser
{

    protected $config;

    protected $utilityHelper;

    public function __construct(Config $config, UtilityHelper $utilityHelper)
    {
        $this->config = $config;
        $this->utilityHelper = $utilityHelper;
    }

    public function parseItem($item, $fields = [])
    {
        $contentType = (string) $item->getContenttype();

        /*if (empty($fields)) {
            $fields = array_keys($item->getContenttype()['fields']);
            //$fields = array_keys($item->_fields);
        }*/

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

        //@todo Create individual parsers for different field types
        //$fieldCollection = FieldFactory::build($item, $fields);

        foreach ($fields as $key => $field) {
            if ($data = $item->get($field)) {
                //Exclude relationships
                if (!$item->get($field) instanceof Relations) {
                    $attributes[$field] = $item->get($field);
                }
            }

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
            
            if (in_array($field, ['datepublish', 'datecreated', 'datechanged', 'datedepublish']) && $this->config->getDateIso()) {
                //Verify not NULL
                if ($data) {
                    $attributes[$field] = $this->utilityHelper->dateISO($attributes[$field]);
                }
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
}
