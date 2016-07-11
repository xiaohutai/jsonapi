<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field;

use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\Generic;
use Bolt\Storage\Entity\Content;

class FieldFactory
{
    protected static $generic = ['text', 'slug', 'html'];

    public static function build(Content $item, $fields)
    {
        $fieldCollection = new FieldCollection([]);

        foreach ($fields as $label => $field) {
            $type = $field['type'];
            $data = $item->get($label);
            
            if (in_array($type, self::$generic)) {
                $typeInstance = new Generic($label, $data);
                $fieldCollection->add($typeInstance);
            }
        }

        foreach ($item->getTaxonomy() as $field) {
            $type = $field->getTaxonomytype();
            $slug = $field->getSlug();
            $route = '/' . $type . '/' . $slug;
            $attributes['taxonomy'][$type][$route] = $field->getName();
        }

        foreach ($item->getRelation() as $relation) {

        }

        return $fieldCollection;
    }
}
