<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiException;
use Doctrine\Common\Collections\ArrayCollection;

class ParameterCollection extends ArrayCollection
{

    /**
     * Quick function to output all query parameters to an array
     *
     * @return array
     */
    public function getQueryParameters()
    {
        $queryParameters = [];

        //Loop through the parameters and add them to an array
        foreach ($this as $key => $value) {
            if (in_array($key, ['sort', 'filters', 'contains'])) {
                $queryParameters = array_merge($value->getParameter(), $queryParameters);
            }
        }

        //Manually add limit to array
        $queryParameters = array_merge(['limit' => $this->get('page')->getSize()], $queryParameters);

        return $queryParameters;
    }

    public function getParametersByType($type)
    {
        if ($parameters = $this->get($type)){
            return $parameters->getParameter();
        }

        throw new ApiException('Invalid item fetched!');
    }
}

