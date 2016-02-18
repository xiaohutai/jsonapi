<?php

namespace JSONAPI\Provider;

use JSONAPI\Config\Config;
use JSONAPI\Helpers\APIHelper;
use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Class APIProvider
 * @package JSONAPI\Provider
 */
class APIProvider implements ServiceProviderInterface
{

    /** @var array */
    private $config;
    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     * @param Application $app
     */
    public function register(Application $app)
    {

        $app['jsonapi.config'] = $app->share(
            function ($app) {
                return new Config($this->config, $app);
            }
        );

        $app['jsonapi.apihelper'] = $app->share(
            function ($app) {
                return new APIHelper($app);
            }
        );

    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     * @param Application $app
     */
    public function boot(Application $app)
    {
    }
}