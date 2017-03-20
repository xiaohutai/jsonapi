<?php
namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;

/**
 * Class ContentController
 *
 * @package JSONAPI\Controllers
 */
class ContentController implements ControllerProviderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * ContentController constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
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

        $ctr->get('/menu', [$app['jsonapi.action.menu'], 'handle'])
            ->bind('jsonapi.menu');

        $ctr->get('/taxonomy', [$app['jsonapi.action.taxonomy'], 'handle'])
            ->bind('jsonapi.taxonomy');

        $ctr->get('/search', [$app['jsonapi.action.search'], 'handle'])
            ->bind('jsonapi.searchAll')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get('/{contentType}/search', [$app['jsonapi.action.search'], 'handle'])
            ->bind('jsonapi.searchContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get('/{contentType}/{slug}/{relatedContentType}', [$app['jsonapi.action.single'], 'handle'])
            ->value('relatedContentType', null)
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('jsonapi.singleContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get('/{contentType}', [$app['jsonapi.action.contentlist'], 'handle'])
            ->bind('jsonapi.listContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        return $ctr;
    }
}
