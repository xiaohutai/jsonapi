<?php

namespace Bolt\Extension\Bolt\JsonApi\Provider;

use Bolt\Composer\Package;
use Bolt\Composer\PackageCollection;
use Bolt\Extension\Bolt\JsonApi\Action\ContentListAction;
use Bolt\Extension\Bolt\JsonApi\Action\MenuAction;
use Bolt\Extension\Bolt\JsonApi\Action\SearchAction;
use Bolt\Extension\Bolt\JsonApi\Action\SingleAction;
use Bolt\Extension\Bolt\JsonApi\Action\TaxonomyAction;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\JSONAPIConverter;
use Bolt\Extension\Bolt\JsonApi\Helpers\DataLinks;
use Bolt\Extension\Bolt\JsonApi\Parser\Parser;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler\Directive\PagerHandler;
use Bolt\Extension\Bolt\JsonApi\Storage\Query\Handler\PagingHandler;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Bolt\Storage\Query\ContentQueryParser;
use Bolt\Extension\Bolt\JsonApi\Action\RootAction;


/**
 * Class APIProvider
 *
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
     *
     * @param Application $app
     */
    public function register(Application $app)
    {

        /**
         * The main configuration class
         */
        $app['jsonapi.config'] = $app->share(
            function ($app) {
                return new Config($this->config, $app);
            }
        );

        /**
         * Param converter to handle JSONAPI spec.
         * For example:
         *  filters, contains, sort, includes, page[number], and page[size]
         */
        $app['jsonapi.converter'] = $app->share(
            function ($app) {
                return new JSONAPIConverter(
                    $app['jsonapi.config'],
                    $app['storage.metadata']
                );
            }
        );

        /**
         * Class to handle parsing of individual items
         *
         * @todo Refactor to include individual parsers based upon the field type
         */
        $app['jsonapi.parser'] = $app->share(
            function ($app) {
                return new Parser(
                    $app['jsonapi.config'],
                    $app['resources'],
                    $app['storage.metadata'],
                    $app['users']
                );
            }
        );

        /**
         * Simple class to handle linking to related items and the current content type
         *
         * @todo Refactor...
         */
        $app['jsonapi.datalinks'] = $app->share(
            function ($app) {
                return new DataLinks($app['jsonapi.config']);
            }
        );

        /**
         * Add controller actions to DI container
         */
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
                    $app['jsonapi.config'],
                    $app['jsonapi.datalinks']
                );
            }
        );

        $app['jsonapi.action.root'] = $app->share(
            function ($app) {
                $boltVersion = $app['bolt_version'];
                /** @var PackageCollection $jsonapiVersion */
                $jsonAPIVersion = $app['extend.manager']->getAllPackages();
                /** @var Package $jsonapiVersion */
                $jsonAPIVersion = $jsonAPIVersion->get('bolt/jsonapi');
                //Get exact version
                $jsonAPIVersion = $jsonAPIVersion->jsonSerialize()['version'];

                return new RootAction(
                    $app['config'],
                    $app['jsonapi.config'],
                    $boltVersion,
                    $jsonAPIVersion
                );
            }
        );

        $app['jsonapi.action.taxonomy'] = $app->share(
            function ($app) {
                return new TaxonomyAction(
                    $app['config'],
                    $app['jsonapi.config']
                );
            }
        );

        /**
         * Add repository and query parsers to handle pagination to DI
         */
        $app['storage.content_repository'] = $app->protect(
            function ($classMetadata) use ($app) {
                $repoClass = 'Bolt\Extension\Bolt\JsonApi\Storage\Repository';
                $repo = new $repoClass($app['storage'], $classMetadata);
                $repo->setLegacyService($app['storage.legacy_service']);

                return $repo;
            }
        );

        $app['query.parser'] = $app->share(
            $app->extend('query.parser', function (ContentQueryParser $parser) {
                $parser->addDirectiveHandler('paginate', new PagerHandler());
                $parser->addHandler('pager', new PagingHandler());
                $parser->addOperation('pager');

                return $parser;
            })
        );
    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
    }
}
