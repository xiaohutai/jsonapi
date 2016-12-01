<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;

/**
 * Class Contains
 *
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type
 */
class Contains extends AbstractParameter
{
    /** @var array $contain */
    protected $contains;

    /**
     * Parameter example: contains[body]=test,test2
     *
     * @return $this
     */
    public function convertRequest()
    {
        $this->contains = [];

        if ($this->values) {
            foreach ($this->values as $field => $value) {
                if (! $this->isValidField($field)) {
                    throw new ApiInvalidRequestException(
                        "Parameter [$field] does not exist for contenttype with name [$this->contentType]."
                    );
                }

                //Get all the values
                $values = explode(',', $value);
                $newValues = [];

                //Loop through the values and setup array as LIKE
                foreach ($values as $index => $parameter) {
                    $newValues[$index] = '%' . $parameter . '%';
                }

                $this->contains[$field] = implode(' || ', $newValues);
            }
        }

        return $this;
    }

    public function findConfigValues()
    {
        // TODO: Implement findConfigValues() method.
    }

    /**
     * @return array
     */
    public function getParameter()
    {
        return $this->getContains();
    }

    /**
     * @return array
     */
    public function getContains()
    {
        return $this->contains;
    }

    /**
     * @param array $contains
     *
     * @return Contains
     */
    public function setContains($contains)
    {
        $this->contains = $contains;

        return $this;
    }
}
