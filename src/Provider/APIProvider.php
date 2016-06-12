<?php

namespace Bolt\Extension\Bolt\JsonApi\Provider;

use Bolt\Extension\Bolt\JsonApi\Action\ContentListAction;
use Bolt\Extension\Bolt\JsonApi\Action\MenuAction;
use Bolt\Extension\Bolt\JsonApi\Action\SearchAction;
use Bolt\Extension\Bolt\JsonApi\Action\SingleAction;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\JSONAPIConverter;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Helpers\DataLinks;
use Bolt\Extension\Bolt\JsonApi\Helpers\UtilityHelper;
use Bolt\Extension\Bolt\JsonApi\Parser\Parser;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler\Directive\PagerHandler;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler\PagingHandler;
use Bolt\Storage\Mapping\MetadataDriver;
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

        $app['jsonapi.utilityhelper'] = $app->share(
            function ($app) {
                return new UtilityHelper($app, $app['jsonapi.config']);
            }
        );

        $app['jsonapi.apihelper'] = $app->share(
            function ($app) {
                return new APIHelper($app, $app['jsonapi.config'], $app['jsonapi.utilityhelper']);
            }
        );

        $app['jsonapi.converter'] = $app->share(
            function ($app) {
                return new JSONAPIConverter(
                    $app['jsonapi.apihelper'],
                    $app['jsonapi.config'],
                    $app['storage.metadata']
                );
            }
        );

        $app['jsonapi.parser'] = $app->share(
            function ($app) {
                return new Parser($app['jsonapi.config'], $app['jsonapi.utilityhelper']);
            }
        );

        $app['jsonapi.datalinks'] = $app->share(
            function ($app) {
                return new DataLinks($app['jsonapi.config']);
            }
        );

        $app['jsonapi.action.contentlist'] = $app->share(
            function ($app) {
                return new ContentListAction(
                    $app['query'],
                    $app['jsonapi.parser'],
                    $app['jsonapi.datalinks'],
                    $app['jsonapi.config']
                );
            }
        );

        $app['jsonapi.action.search'] = $app->share(
            function ($app) {
                return new SearchAction(
                    $app['query'],
                    $app['jsonapi.parser'],
                    $app['jsonapi.datalinks'],
                    $app['jsonapi.config']
                );
            }
        );

        $app['jsonapi.action.single'] = $app->share(
            function ($app) {
                return new SingleAction(
                    $app['query'],
                    $app['jsonapi.parser'],
                    $app['jsonapi.datalinks'],
                    $app['jsonapi.config']
                );
            }
        );

        $app['jsonapi.action.menu'] = $app->share(
            function ($app) {
                return new MenuAction(
                    $app['config'],
                    $app['jsonapi.config']
                );
            }
        );

        $app['storage.content_repository'] = $app->protect(
            function ($classMetadata) use ($app) {
                $repoClass = 'Bolt\Extension\Bolt\JsonApi\Storage\Repository';
                $repo = new $repoClass($app['storage'], $classMetadata);
                $repo->setLegacyService($app['storage.legacy_service']);
                return $repo;
            }
        );

        $app['query.parser']->addDirectiveHandler('paginate', new PagerHandler());
        $app['query.parser']->addHandler('pager', new PagingHandler());
        $app['query.parser']->addOperation('pager');

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
