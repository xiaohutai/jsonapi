<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;

class Contains extends AbstractParameter
{
    /** @var array $contain */
    protected $contains;

    public function convertRequest()
    {
        $this->contains = [];

        if ($this->values) {
            foreach ($this->values as $key => $value) {
                if (! $this->isValidField($key)) {
                    throw new ApiInvalidRequestException(
                        "Parameter [$key] does not exist for contenttype with name [$this->contentType]."
                    );
                }
                $values = explode(',', $value);
                $newValues = [];

                foreach ($values as $index => $value) {
                    $newValues[$index] = '%' . $value . '%';
                }

                $this->contains[$key] = implode(' || ', $newValues);
            }
        }

        return $this;
    }

    public function findConfigValues()
    {
        // TODO: Implement findConfigValues() method.
    }

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
     * @return Contains
     */
    public function setContains($contains)
    {
        $this->contains = $contains;

        return $this;
    }
}

