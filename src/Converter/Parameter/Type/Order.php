<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

class Order extends AbstractParameter
{
    const DEFAULT_ORDER = 'id';

    /** @var string $order */
    protected $order;

    public function convertRequest()
    {
        $order = $this->values;

        //Get sort order
        $this->order = $order ? $order : self::DEFAULT_ORDER;

        return $this;
    }

    public function findConfigValues()
    {
    }

    public function getParameter()
    {
        return ['order' => $this->getOrder()];
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param string $order
     *
     * @return Order
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }
}
