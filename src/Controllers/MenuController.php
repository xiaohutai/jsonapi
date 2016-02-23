<?php

namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MenuController
 * @package JSONAPI\Controllers
 */
class MenuController implements ControllerProviderInterface
{
    /**
     * @var Config
     */
    private $config;
    /**
     * @var APIHelper
     */
    private $APIHelper;

    /**
     * MenuController constructor.
     * @param Config $config
     * @param APIHelper $APIHelper
     */
    public function __construct(Config $config, APIHelper $APIHelper)
    {
        $this->config = $config;
        $this->APIHelper = $APIHelper;
    }

    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app)
    {
        /**
         * @var $ctr \Silex\ControllerCollection
         */
        $ctr = $app['controllers_factory'];

        $app->get($this->config->getBase()."/menu", [$this, "listMenus"])->bind('jsonapi.menu');

        return $ctr;
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function listMenus(Request $request, Application $app)
    {
        $this->config->setCurrentRequest($request);

        $name = '';
        if ($q = $request->get('q')) {
            $name = "/$q";
        }

        $menu = $app['config']->get('menu'.$name, false);
        if ($menu) {
            return new ApiResponse([
                'data' => $menu
            ], $this->config);
        }
        return new ApiNotFoundResponse([
            'detail' => "Menu with name [$q] not found."
        ], $this->config);
    }

}