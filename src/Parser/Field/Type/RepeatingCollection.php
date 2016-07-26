<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

use Doctrine\Common\Collections\ArrayCollection;

class RepeatingCollection extends ArrayCollection implements TypeInterface
{

    protected $type;

    protected $value;

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     * @return RepeatingCollection
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     * @return RepeatingCollection
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    public function render()
    {
        $attributes = [];
        $multidimensional = [];

        foreach ($this as $repeatingFields) {
            foreach ($repeatingFields as $fieldCollection) {
                foreach ($fieldCollection as $field) {
                    $attributes[$field->getType()] = $field->render();
                }
                $multidimensional[] = $attributes;
            }
        }

        return $multidimensional;
    }

    public function isTaxonomy()
    {
        return false;
    }
}
