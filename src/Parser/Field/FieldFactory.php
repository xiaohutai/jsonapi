<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field;

use Bolt\Extension\Bolt\JsonApi\Parser\Field\Type\Generic;

class FieldFactory
{

    public static function build($type, $value)
    {

        switch (gettype($value)) {
            case 'string':
            case 'integer':
                $type = new Generic($type, $value);
        }
    }
}
