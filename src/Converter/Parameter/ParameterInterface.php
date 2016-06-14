<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter;

/**
 * Interface ParameterInterface
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter
 */
interface ParameterInterface
{

    /**
     * Converts the request to a readable type for Bolt.
     * @return mixed
     */
    public function convertRequest();

    /**
     * Gets all default values that are configured in the configuration file
     * @return mixed
     */
    public function findConfigValues();

    /**
     * Merges the request and configuration together for Bolt.
     * @return mixed
     */
    public function getParameter();
}
