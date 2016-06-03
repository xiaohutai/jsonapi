<?php
namespace Bolt\Extension\Bolt\JsonApi\Helpers;
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

    /**
     * UtilityHelper constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
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
        $config = $this->app['extensions']->getEnabled()['JSON API']->getConfig();
        return sprintf('%s/thumbs/%sx%s/%s',
            $this->app['paths']['canonical'],
            $config['thumbnail']['width'],
            $config['thumbnail']['height'],
            $filename
        );
    }
}
