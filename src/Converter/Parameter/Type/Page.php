<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

class Page extends AbstractParameter
{
    const DEFAULT_PAGE_SIZE = 10;
    const DEFAULT_PAGE_NUMBER = 1;

    /** @var int $size */
    protected $size;

    /** @var int $number */
    protected $number;

    public function convertRequest()
    {
        //False will return 0, which will be less than 1, so it should default correctly.
        $size = intval($this->values['size']);
        $number = intval($this->values['number']);

        // Get limit for query
        $this->size = $size >= 1 ? $size : self::DEFAULT_PAGE_SIZE;

        //Get page number for query
        $this->number = $number >= 1 ? $number : self::DEFAULT_PAGE_NUMBER;

        return $this;
    }

    public function findConfigValues() {

    }

    public function getParameter()
    {
        return ['paginate' => $this];
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     * @return Page
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param int $number
     * @return Page
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }
}

