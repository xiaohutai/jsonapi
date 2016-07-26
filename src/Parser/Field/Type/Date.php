<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Carbon\Carbon;

class Date extends AbstractType
{
    /** @var Config $config */
    protected $config;

    /**
     * Date constructor.
     * @param $type
     * @param $value
     * @param Config $config
     */
    public function __construct($type, $value, Config $config)
    {
        parent::__construct($type, $value);
        $this->config = $config;
    }

    public function render()
    {
        /** @var Carbon $date */
        $date = $this->getValue();

        return $date->format('c');
    }
}
