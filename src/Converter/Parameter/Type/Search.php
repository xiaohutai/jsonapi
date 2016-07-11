<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

/**
 * Class Search
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type
 */
class Search extends AbstractParameter
{

    /** @var string $search */
    protected $search;

    /**
     * Parameter example: q=test
     * @return $this
     */
    public function convertRequest()
    {
        $this->search = $this->values;

        return $this;
    }

    public function findConfigValues()
    {

    }

    /**
     * @return array
     */
    public function getParameter()
    {
        return ['filter' => $this->getSearch()];
    }

    /**
     * @return string
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @param string $search
     * @return Search
     */
    public function setSearch($search)
    {
        $this->search = $search;
        return $this;
    }
}
