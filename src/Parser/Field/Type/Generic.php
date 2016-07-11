<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

class Generic
{

    protected $type;

    protected $value;

    public function __construct($type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     * @return Generic
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
     * @return Generic
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }
}
