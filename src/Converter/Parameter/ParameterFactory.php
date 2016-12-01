<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\AbstractParameter;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type\Fields;
use Bolt\Storage\Mapping\MetadataDriver;

/**
 * Class ParameterFactory
 *
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter
 */
class ParameterFactory
{
    /**
     * Loop through all current parameters from param converter. See if we have a class for it, initialize the class and
     *  run convertRequest to put variables where they need to be, and add to the array.
     *
     * @param $parameters
     * @param Config         $config
     * @param MetadataDriver $metadata
     *
     * @return ParameterCollection
     */
    public static function build($parameters, Config $config, MetadataDriver $metadata)
    {
        $contentType = $parameters['contentType'];

        foreach ($parameters as $key => $value) {
            //Get FQDN
            $class = __NAMESPACE__ . '\\Type\\' . ucfirst($key);

            //Check if it exists
            if (class_exists($class)) {
                //Call static function to initialize instance
                $parameterInstance = call_user_func_array(
                    [$class, 'initialize'],
                    [$contentType, $value, $config, $metadata]
                );
                if ($parameterInstance instanceof AbstractParameter) {
                    //Run convertRequest to store values in correct variables for getters and setters...
                    $parameterInstance->convertRequest();
                    $parameter[$key] = $parameterInstance;
                }
            }
        }

        $parameterCollection = new ParameterCollection($parameter);

        $includes = $parameterCollection->getParametersByType('includes');

        //Loop through the includes to get fields for each one...
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
