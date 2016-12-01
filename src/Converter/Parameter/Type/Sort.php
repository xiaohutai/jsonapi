<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

/**
 * Class Sort
 *
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type
 */
class Sort extends AbstractParameter
{
    const DEFAULT_SORT = 'id';

    /** @var string $sort */
    protected $sort;

    /**
     * Parameter example: sort=id,-body
     *
     * @return $this
     */
    public function convertRequest()
    {
        $sort = $this->values;

        //Get sort order
        $this->sort = $sort ? $sort : self::DEFAULT_SORT;

        return $this;
    }

    public function findConfigValues()
    {
        return $this->config->getSort($this->contentType);
    }

    /**
     * @return array
     */
    public function getParameter()
    {
        return ['order' => $this->getSort()];
    }

    /**
     * @return string
     */
    public function getSort()
    {
        if (! empty($this->findConfigValues())) {
            return $this->findConfigValues() . ',' . $this->sort;
        }

        return $this->sort;
    }

    /**
     * @param string $sort
     *
     * @return Sort
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
    }
}
