<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field;

use Bolt\Configuration\ResourceManager;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\Date;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\File;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\Generic;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\RepeatingCollection;
use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\RepeatingFieldCollection;
use Bolt\Storage\Collection\Relations;
use Bolt\Storage\Collection\Taxonomy;
use Bolt\Storage\Entity\Entity;
use Carbon\Carbon;

class FieldFactory
{
    /** @var array of file types that will be modified as files */
    protected static $fileTypes = ['image', 'imagelist', 'file', 'filelist'];

    /**
     * @param ResourceManager $resourceManager
     * @param Config $config
     * @param $fields
     * @param Entity|null $item
     * @param RepeatingFieldCollection|null $fieldCollection
     * @return FieldCollection|
     * The function goes and builds AbstractTypes. RepeatingFieldCollection
     * is a unique case and doesn't extend AbstractTypes, but does have some
     * of the same attributes. It loads classes based upon the field type.
     */
    public static function build(
        $metadata,
        ResourceManager $resourceManager,
        Config $config,
        $fields,
        $item = null,
        RepeatingFieldCollection $fieldCollection = null
    ) {
        if (! $fieldCollection) {
            $fieldCollection = new FieldCollection([]);
        }

        foreach ($fields as $label => $field) {
            if ($fieldCollection instanceof RepeatingFieldCollection) {
                $data = $field->getValue();
                $field = $field->getFieldtype();
            } else {
                $data = $item->get($field);
                if (isset($metadata['fields'])) {
                    if (isset($metadata['fields'][$field])) {
                        $fieldType = $metadata['fields'][$field]['data']['type'];
                    }
                }
            }

            if ($data instanceof Taxonomy) {
                $type = new \Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\Taxonomy($field, $data);
            } elseif ($data instanceof \Bolt\Storage\Field\Collection\RepeatingFieldCollection) {
                foreach ($data as $index => $fields) {
                    $repeatingFieldCollection = new RepeatingFieldCollection([]);
                    /** @var RepeatingFieldCollection[] $collection */
                    $repeatingFieldCollection = self::build(
                        $metadata,
                        $resourceManager,
                        $config,
                        $fields,
                        null,
                        $repeatingFieldCollection
                    );
                    //We want to append repeating fields to a repeating collection if it is the same type
                    if ($repeatingCollection) {
                        $repeatingCollection->add([$repeatingFieldCollection]);
                    } else {
                        $repeatingCollection = new RepeatingCollection();
                        $repeatingCollection->setType($field);
                        $repeatingCollection->add([$repeatingFieldCollection]);
                        $fieldCollection->add($repeatingCollection);
                    }
                }
            } elseif ($data instanceof Carbon) {
                $type = new Date($field, $data, $config);
            } elseif (!$data instanceof Relations) {
                if (in_array($fieldType, self::$fileTypes)) {
                    $type = new File($field, $data, $resourceManager, $config);
                    $type->setFieldType($fieldType);
                } else {
                    //We need to check if the label is an int. If it isn't then we'll use that for type (repeaters).
                    if (is_int($label)) {
                        $type = new Generic($field, $data);
                    } else {
                        $type = new Generic($label, $data);
                    }
                }
            }

            //Must NOT be a repeater
            if (! $repeatingFieldCollection) {
                $repeatingFieldCollection = null;
                $fieldCollection->add($type);
            }
        }

        return $fieldCollection;
    }
}
