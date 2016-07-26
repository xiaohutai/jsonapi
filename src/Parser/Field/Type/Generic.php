<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

class Generic extends AbstractType
{
    public function render()
    {
        return $this->getValue();
    }
}
