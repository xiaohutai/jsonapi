<?php
/**
 * JSON API extension for Bolt. Forked from the JSONAccess extension.
 *
 * @author Tobias Dammers <tobias@twokings.nl>
 * @author Bob den Otter <bob@twokings.nl>
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 * @author Dennis Snijder <Dennissnijder97@gmail.com>
 */

namespace Bolt\Extension\Bolt\JsonApi;

use Bolt\Extension\Bolt\JsonApi\Controllers\ContentController;
use Bolt\Extension\Bolt\JsonApi\Controllers\MenuController;
use Bolt\Extension\Bolt\JsonApi\Provider\APIProvider;

/**
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 */
class Extension extends \Bolt\BaseExtension
{

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



        /* Mount the controllers! */
        $this->app->mount($this->app['jsonapi.config']->getBase(),
            new MenuController($this->app['jsonapi.config'], $this->app['jsonapi.apihelper']));

        $this->app->mount($this->app['jsonapi.config']->getBase(),
           new ContentController($this->app['jsonapi.config'], $this->app['jsonapi.apihelper']));
    }


}
