<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;

class Filters extends AbstractParameter
{
    /** @var array $filters */
    protected $filters;

    public function convertRequest()
    {
        $this->filters = [];

        if ($this->values) {
            foreach ($this->values as $key => $value) {
                if (! $this->isValidField($key)) {
                    throw new ApiInvalidRequestException(
                        "Parameter [$key] does not exist for contenttype with name [$this->contentType]."
                    );
                }
                $this->filters[$key] = str_replace(',', ' || ', $value);
            }
        }

        return $this;
    }

    public function findConfigValues()
    {
        return $this->config->getWhereClauses($this->contentType);
    }

    public function getParameter()
    {
        return $this->getFilters();
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return array_merge($this->filters, $this->findConfigValues(), []);
    }

    /**
     * @param array $filters
     * @return Filters
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }
}
