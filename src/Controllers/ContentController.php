<?php
namespace Bolt\Extension\Bolt\JsonApi\Controllers;

use Bolt\Content;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\JSONAPIConverter;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterInterface;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiInvalidRequestResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Storage\Query\QueryResultset;
use Doctrine\Common\Collections\Collection;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ContentController
 * @package JSONAPI\Controllers
 */
class ContentController implements ControllerProviderInterface
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
     * ContentController constructor.
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

        $ctr->get("/menu", [$app['jsonapi.action.menu'], "handle"])
            ->bind('jsonapi.menu');


        $ctr->get("/{contentType}/search", [$app['jsonapi.action.search'], "handle"])
            ->bind('jsonapi.searchContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get("/{contentType}/{slug}/{relatedContentType}", [$app['jsonapi.action.single'], 'handle'])
            ->value('relatedContentType', null)
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('jsonapi.singleContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

        $ctr->get("/{contentType}", [$app['jsonapi.action.contentlist'], "handle"])
            ->bind('jsonapi.listContent')
            ->convert('parameters', 'jsonapi.converter:grabParameters');

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

        throw new ApiNotFoundException(
            "Menu with name [$q] not found."
        );

    }
}
