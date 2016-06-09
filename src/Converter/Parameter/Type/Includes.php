<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;

class Includes extends AbstractParameter
{
    /** @var array $includes */
    protected $includes;

    protected $fields;

    public function convertRequest()
    {
        $this->includes = [];

        if ($this->values) {
            $includes = explode(',', $this->values);
            foreach ($includes as $ct) {
                if (! $this->isValidContentType($this->contentType)) {
                    throw new ApiInvalidRequestException(
                        "Content type with name [$this->contentType] requested in include not found."
                    );
                }
                
                //This should throw an error if false I think - *ponders*
                if ($this->isValidField($ct)) {
                    $this->includes[] = $ct;
                }
            }
        }

        return $this;
    }

    public function findConfigValues()
    {
        // TODO: Implement findConfigValues() method.
    }

    public function setFields($contentType, $fields)
    {
        $this->fields[$contentType] = $fields;

        return $this;
    }

    public function getFieldsByContentType($contentType)
    {
        return $this->fields[$contentType];
    }

    public function getParameter()
    {
        return $this->getIncludes();
    }

    /**
     * @return array
     */
    public function getIncludes()
    {
        return $this->includes;
    }

    /**
     * @param array $includes
     * @return Includes
     */
    public function setIncludes($includes)
    {
        $this->includes = $includes;

        return $this;
    }
}
