<?php
namespace JSONAPI\Helpers;

use Symfony\Component\Console\Application;

class UtilityHelper
{

    public function __construct(Application $app)
    {
    }

    private function dateISO($date)
    {
        $dateObject = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
        return($dateObject->format('c'));
    }

    private function makeAbsolutePathToImage($filename = '')
    {
        return sprintf('%s%s%s',
            $this->app['paths']['canonical'],
            $this->app['paths']['files'],
            $filename
        );
    }

    private function makeAbsolutePathToThumbnail($filename = '')
    {
        return sprintf('%s/thumbs/%sx%s/%s',
            $this->app['paths']['canonical'],
            $this->app['config']['thumbnail']['width'],
            $this->app['config']['thumbnail']['height'],
            $filename
        );
    }
}