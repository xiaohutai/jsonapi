<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Contains;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\ContentType;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Fields;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Filters;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Includes;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Order;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Page;
use Bolt\Storage\Mapping\MetadataDriver;

class ParameterFactory
{

    public static function build($parameters, Config $config, MetadataDriver $metadata)
    {
        $contentType = $parameters['contentType'];

        foreach ($parameters as $key => $value) {
            $newParameter = false;

            switch($key) {
                case 'page':
                    $newParameter = new Page($contentType, $value, $config, $metadata);
                    $newParameter->convertRequest();
                    break;
                case 'sort':
                    $newParameter = new Order($contentType, $value, $config, $metadata);
                    $newParameter->convertRequest();
                    break;
                case 'filters':
                    $newParameter = new Filters($contentType, $value, $config, $metadata);
                    $newParameter->convertRequest();
                    break;
                case 'contains':
                    $newParameter = new Contains($contentType, $value, $config, $metadata);
                    $newParameter->convertRequest();
                    break;
                case 'includes':
                    $newParameter = new Includes($contentType, $value, $config, $metadata);
                    $newParameter->convertRequest();
                    break;
                case 'contentType':
                    $newParameter = new ContentType($contentType, $value, $config, $metadata);
                    $newParameter->convertRequest();
                    break;
                case 'fields':
                    $newParameter = new Fields($contentType, $value, $config, $metadata);
                    $newParameter->convertRequest();
                    break;
            }

            if ($newParameter) {
                $parameter[$key] = $newParameter;
            }
        }

        $parameterCollection = new ParameterCollection($parameter);

        $includes = $parameterCollection->getParametersByType('includes');
        
        foreach ($includes as $include) {
            //Grab all fields that should be displayed based upon the 
            //  content type.
            $newField = new Fields($include, $parameters['fields'], $config, $metadata);
            $newField->convertRequest();
            $parameterCollection->get('includes')->setFields($include, $newField);
        }

        return $parameterCollection;

    }

}

