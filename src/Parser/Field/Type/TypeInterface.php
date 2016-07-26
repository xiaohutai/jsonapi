<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

interface TypeInterface
{

    public function getType();

    public function setType($type);

    public function getValue();

    public function setValue($value);

    public function render();
}
