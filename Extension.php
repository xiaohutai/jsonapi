<?php
/**
 * JSON API extension for Bolt. Forked from the JSONAccess extension.
 *
 * @author Tobias Dammers <tobias@twokings.nl>
 * @author Bob den Otter <bob@twokings.nl>
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */

namespace JSONAPI;

use \Bolt\Helpers\Arr;
use Bolt\Helpers\Menu;
use JSONAPI\Controllers\ContentController;
use JSONAPI\Controllers\MenuController;
use JSONAPI\Helpers\APIHelper;
use JSONAPI\Helpers\ConfigHelper;
use JSONAPI\Provider\APIProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 */
class Extension extends \Bolt\BaseExtension
{

    /**
     * @var Request
     */
    public static $request;


    public static $base = '/json';
    public static $basePath;

    public static $paginationNumberKey = 'page'; // todo: page[number]
    public static $paginationSizeKey = 'limit';  // todo: page[size]

    /**
     * Returns the name of this extension.
     *
     * @return string The name of this extension.
     */
    public function getName()
    {
        return "JSON API";
    }

    /**
     * Initializes the extension and mounts the endpoints
     */
    public function initialize()
    {

        $this->app->register(new APIProvider($this->config));

        $this->app->mount($this->app['jsonapi.config']->getBase()."/menu",
            new MenuController($this->app['jsonapi.config'], $this->app['jsonapi.apihelper'], $this->app));

       $this->app->mount($this->app['jsonapi.config']->getBase()."/{contenttype}",
           new ContentController($this->app['jsonapi.config'], $this->app['jsonapi.apihelper'], $this->app));
    }


}
