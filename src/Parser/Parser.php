<?php

namespace Bolt\Extension\Bolt\JsonApi\Parser;

use Bolt\Configuration\ResourceManager;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Fields;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\FieldCollection;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\FieldFactory;
use Bolt\Storage\Entity\Relations;
use Bolt\Storage\Mapping\MetadataDriver;
use Bolt\Users;

class Parser
{
    /** @var Config $config */
    protected $config;

    /** @var ResourceManager $resourceManager */
    protected $resourceManager;

    /** @var MetadataDriver $metadata */
    protected $metadata;

    /** @var Users $users */
    protected $users;

    public function __construct(Config $config, ResourceManager $resourceManager, MetadataDriver $metadata, Users $users)
    {
        $this->config = $config;
        $this->resourceManager = $resourceManager;
        $this->metadata = $metadata;
        $this->users = $users;
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
            'id'   => strval($id),
            'type' => $contentType,
        ];
        $fields = array_unique($fields);

        //Get field type information
        $metadata = $this->metadata->getClassMetadata($contentType);

        /** @var FieldCollection $fieldCollection */
        $fieldCollection = FieldFactory::build(
            $metadata,
            $this->resourceManager,
            $this->config,
            $fields,
            $item
        );

        /** @var array $attributes */
        $attributes = $fieldCollection->getAttributes();

        if (!empty($attributes)) {
            // Check for TemplateFields and show it properly
            if (isset($attributes['templatefields']) && !empty($attributes['templatefields'])) {
                $attributes['templatefields'] = $attributes['templatefields']->jsonSerialize();
            }

            // Recursively walk the array..
            array_walk_recursive($attributes, function (&$item) {
                // Make sure that any \Twig_Markup objects are cast to plain strings.
                if ($item instanceof \Twig_Markup) {
                    $item = $item->__toString();
                }

                // Handle replacements.
                $replacements = $this->config->getReplacements();
                if (!empty($replacements)) {
                    foreach ($replacements as $from => $to) {
                        $item = str_replace($from, $to, $item);
                    }
                }
            });

            $values['attributes'] = $attributes;
        }

        if ($this->config->isEnableDisplayNames() && isset($values['attributes']['ownerid'])) {
            $ownerid = $values['attributes']['ownerid'];
            $owner = $this->users->getUser($ownerid);

            if ($owner) {
                $values['attributes']['ownerdisplayname'] = $owner['displayname'];
            }
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
                $relationships[] = $this->relatedJSONParser($relatedType, $data, $contentType);
            }
            $values['relationships'] = $relationships;
        }

        return $values;
    }

    /**
     * @param Relations $relatedType
     */
    protected function relatedJSONParser($relatedType, $data, $contentType)
    {
        $toID = $relatedType->getToId();
        $fromID = $relatedType->getFromId();
        $fromType = $relatedType->getFromContenttype();
        $toType = $relatedType->getToContenttype();

        if ($contentType === $fromType) {
            $toContentType = $toType;
            $id = $toID;
        } else {
            $toContentType = $fromType;
            $id = $fromID;
        }

        $data[] = [
            'type' => $toContentType,
            'id'   => $id,
        ];

        $relationships[$toContentType] = [
            'links' => [
                'self' => $this->config->getBasePath() . "/$toContentType/$id",
                'related' => $this->config->getBasePath() . "/$toContentType/$id/$contentType"
            ],
            'data' => $data,
        ];

        return $relationships;
    }

}
