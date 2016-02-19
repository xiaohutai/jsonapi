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
use JSONAPI\Controllers\ContentController;
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

    public function initialize()
    {

        $this->app->register(new APIProvider($this->config));


        $this->app->mount($this->app['jsonapi.config']->getBase()."/{contenttype}",
            new ContentController($this->app['jsonapi.config'], $this->app['jsonapi.apihelper'], $this->app));


        /*
        $this->app->get($this->base."/{contenttype}/{slug}/{relatedContenttype}", [$this, 'jsonapi'])
                  ->value('relatedContenttype', null)
                  ->assert('slug', '[a-zA-Z0-9_\-]+')
                  ->bind('jsonapi');
        $this->app->get($this->base."/{contenttype}", [$this, 'jsonapi_list'])
                  ->bind('jsonapi_list');*/
    }


    /**
     * Fetches a single item or all related items — of which their contenttype is
     * defined in $relatedContenttype — of that single item.
     *
     * @todo split up fetching single item and fetching of related items?
     *
     * @param Request $request
     * @param string $contenttype The name of the contenttype.
     * @param string $slug The slug, preferably a numeric id, but Bolt allows
     *                     slugs in the form of strings as well.
     * @param string $relatedContenttype The name of the related contenttype
     *                                   that is related to $contenttype.
     */
    public function jsonapi(Request $request, $contenttype, $slug, $relatedContenttype)
    {

    }

    /**
     * Fetches menus. Either a list of menus, or a single menu defined by the
     * query string `q`.
     *
     * @todo fetch all the records from the database.
     *
     * @param Request $request
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function jsonapi_menu(Request $request)
    {
        $this->request = $request;

        $name = '';

        if ($q = $request->get('q')) {
            $name = "/$q";
        }

        $menu = $this->app['config']->get('menu'.$name, false);

        if ($menu) {
            return $this->response([
                'data' => $menu
            ]);
        }

        return $this->responseNotFound([
            'detail' => "Menu with name [$q] not found."
        ]);
    }

}
