<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser;

use Bolt\Configuration\ResourceManager;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\FieldFactory;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\FieldCollection;
use Bolt\Storage\EntityProxy;

class Parser
{

    /** @var Config $config */
    protected $config;

    /** @var ResourceManager $resourceManager */
    protected $resourceManager;

    public function __construct(Config $config, ResourceManager $resourceManager)
    {
        $this->config = $config;
        $this->resourceManager = $resourceManager;
    }

    public function parseItem($item, $fields = [])
    {
        $contentType = (string) $item->getContenttype();

        if (empty($fields)) {
            $fields = array_keys($item->getContenttype()['fields']);
            //$fields = array_keys($item->_fields);
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
        $fields = array_unique($fields);

        /** @var FieldCollection $fieldCollection */
        $fieldCollection = FieldFactory::build(
            $this->resourceManager,
            $this->config,
            $fields,
            $item
        );

        /** @var array $attributes */
        $attributes = $fieldCollection->getAttributes();

        if (!empty($attributes)) {
            // Recursively walk the array..
            array_walk_recursive($attributes, function (&$item) {
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
                    'type' => $fromType,
                    'id' => $id
                ];

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
