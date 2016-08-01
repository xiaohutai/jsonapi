<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field;

use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\AbstractType;
use Doctrine\Common\Collections\ArrayCollection;

class FieldCollection extends ArrayCollection
{
    /**
     * @return array
     * This function gets all items of a collection and calls
     * render, which is unique based upon the field type.
     */
    public function getAttributes()
    {
        /** @var array $attributes */
        $attributes = [];

        /** @var AbstractType $attribute */
        foreach ($this as $attribute) {
            /** @var string|array $data */
            $data = $attribute->render();
            /** @var string $type */
            $type = $attribute->getType();

            if ($attribute->isTaxonomy()) {
                $attributes['taxonomy'][$type] =  $data;
            } else {
                $attributes[$type] =  $data;
            }

        }

        return $attributes;
    }
}
