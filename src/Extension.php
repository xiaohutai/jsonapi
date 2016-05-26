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
use Bolt\Extension\SimpleExtension;

/**
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 */
class Extension extends SimpleExtension
{
    public function getServiceProviders()
    {
        $parentProviders = parent::getServiceProviders();
        $localProviders = [
            new APIProvider($this->getConfig()),
        ];

        return $parentProviders + $localProviders;
    }

    protected function registerFrontendControllers()
    {
        $container = $this->getContainer();

        return [
            '/' . $container['jsonapi.config']->getBase() =>
                new MenuController($container['jsonapi.config'], $container['jsonapi.apihelper']),
            '/' . $container['jsonapi.config']->getBase() =>
                new ContentController($container['jsonapi.config'], $container['jsonapi.apihelper']),
        ];
    }
}
