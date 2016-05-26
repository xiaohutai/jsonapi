<?php
namespace Bolt\Extension\Bolt\JsonApi\Helpers;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Silex\Application;


/**
 * Class UtilityHelper
 * @package JSONAPI\Helpers
 */
class UtilityHelper
{

    /**
     * @var Application
     */
    private $app;

    /** @var Config $config */
    private $config;

    /**
     * UtilityHelper constructor.
     * @param Application $app
     * @param Config $config
     */
    public function __construct(Application $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * @param $date
     * @return string
     */
    public function dateISO($date)
    {
        $dateObject = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
        return($dateObject->format('c'));
    }

    /**
     * @param string $filename
     * @return string
     */
    public function makeAbsolutePathToImage($filename = '')
    {
        return sprintf('%s%s%s',
            $this->app['paths']['canonical'],
            $this->app['paths']['files'],
            $filename
        );
    }

    /**
     * @param string $filename
     * @return string
     */
    public function makeAbsolutePathToThumbnail($filename = '')
    {
        return sprintf('%s/thumbs/%sx%s/%s',
            $this->app['paths']['canonical'],
            $this->config->getThumbnail()['width'],
            $this->config->getThumbnail()['height'],
            $filename
        );
    }
}