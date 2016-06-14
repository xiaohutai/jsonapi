<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiException;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Class ParameterCollection
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter
 */
class ParameterCollection extends ArrayCollection
{

    /**
     * Function to output all query parameters in a readable format for Bolt
     * @return array
     */
    public function getQueryParameters()
    {
        $queryParameters = [];

        //Loop through the parameters and add them to an array
        foreach ($this as $key => $value) {
            if (in_array($key, ['order', 'filters', 'contains', 'page'])) {
                $queryParameters = array_merge($value->getParameter(), $queryParameters);
            }
        }

        return $queryParameters;
    }

    /**
     * Calls the getParameter based upon the type
     * @param $type
     * @return mixed
     */
    public function getParametersByType($type)
    {
        if ($parameters = $this->get($type)) {
            return $parameters->getParameter();
        }

        throw new ApiException('Invalid item fetched!');
    }
}
