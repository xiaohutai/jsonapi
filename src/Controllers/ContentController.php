<?php
namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

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

        $ctr->get('/', [$this, 'root'])
            ->bind('jsonapi');

        $ctr->get('/menu', [$this, 'menu'])
            ->bind('jsonapi.menu');

        $ctr->get('/menu/{q}', [$this, 'menu'])
            ->bind('jsonapi.singleMenu');

        $ctr->get('/taxonomy', [$this, 'taxonomy'])
            ->bind('jsonapi.taxonomy');

        $ctr->get('/search', [$this, 'searchAll'])
            ->bind('jsonapi.searchAll')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get('/{contentType}/search', [$this, 'searchContent'])
            ->bind('jsonapi.searchContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get('/{contentType}/{slug}/{relatedContentType}', [$this, 'singleContent'])
            ->value('relatedContentType', null)
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('jsonapi.singleContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get('/{contentType}', [$this, 'listContent'])
            ->bind('jsonapi.listContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        return $ctr;
    }

    /**
     * @param Application $app
     * @param Request     $request
     *
     * @return mixed
     */
    public function root(Application $app, Request $request)
    {
        return $app['jsonapi.action.root']->handle($request);
    }

    /**
     * @param Application $app
     * @param Request     $request
     *
     * @return mixed
     */
    public function menu(Application $app, Request $request)
    {
        return $app['jsonapi.action.menu']->handle($request);
    }

    /**
     * @param Application $app
     * @param Request     $request
     *
     * @return mixed
     */
    public function taxonomy(Application $app, Request $request)
    {
        return $app['jsonapi.action.taxonomy']->handle($request);
    }

    /**
     * @param Application         $app
     * @param Request             $request
     * @param ParameterCollection $parameters
     *
     * @return mixed
     */
    public function searchAll(Application $app, Request $request, ParameterCollection $parameters)
    {
        return $app['jsonapi.action.search']->handle(null, $request, $parameters);
    }

    /**
     * @param Application         $app
     * @param Request             $request
     * @param string              $contentType
     * @param ParameterCollection $parameters
     *
     * @return mixed
     */
    public function searchContent(Application $app, Request $request, $contentType, ParameterCollection $parameters)
    {
        return $app['jsonapi.action.search']->handle($contentType, $request, $parameters);
    }

    /**
     * @param Application         $app
     * @param Request             $request
     * @param string              $contentType
     * @param string              $slug
     * @param string              $relatedContentType
     * @param ParameterCollection $parameters
     *
     * @return mixed
     */
    public function singleContent(Application $app, Request $request, $contentType, $slug, $relatedContentType, ParameterCollection $parameters)
    {
        return $app['jsonapi.action.single']->handle($contentType, $slug, $relatedContentType, $request, $parameters);
    }

    /**
     * @param Application         $app
     * @param Request             $request
     * @param string              $contentType
     * @param ParameterCollection $parameters
     *
     * @return mixed
     */
    public function listContent(Application $app, Request $request, $contentType, ParameterCollection $parameters)
    {
        return $app['jsonapi.action.contentlist']->handle($contentType, $request, $parameters);
    }
}
