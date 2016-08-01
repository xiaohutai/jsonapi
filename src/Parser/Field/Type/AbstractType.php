<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

abstract class AbstractType implements TypeInterface
{

    protected $type;

    protected $value;

    /**
     * AbstractType constructor.
     * @param $type
     * @param $value
     */
    public function __construct($type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return AbstractType
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
     * @return AbstractType
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return mixed
     * Internal function used to display the json representation
     */
    abstract public function render();

    /**
     * @return bool
     */
    public function isTaxonomy()
    {
        return false;
    }
}
