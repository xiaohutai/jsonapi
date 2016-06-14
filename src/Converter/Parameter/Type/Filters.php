<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;

/**
 * Class Filters
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type
 */
class Filters extends AbstractParameter
{
    /** @var array $filters */
    protected $filters;

    /**
     * Parameter example: filter[id]=1,2
     * @return $this
     */
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
                //Replace , with || to filter results correctly
                $this->filters[$key] = str_replace(',', ' || ', $value);
            }
        }

        return $this;
    }

    /**
     * Grab default configuration values
     * @return array
     */
    public function findConfigValues()
    {
        return $this->config->getWhereClauses($this->contentType);
    }

    /**
     * @return array
     */
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
