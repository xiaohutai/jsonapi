<?php
namespace JSONAPI\Controllers;

use JSONAPI\Config\Config;
use JSONAPI\Helpers\APIHelper;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ContentController
 * @package JSONAPI\Controllers
 */
class ContentController extends APIController implements ControllerProviderInterface
{

    /**
     * @var Application
     */
    private $app;

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
     * @param Application $app
     */
    public function __construct(Config $config, APIHelper $APIHelper, Application $app)
    {
        $this->app = $app;
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

        $ctr->get("", [$this, "getContent"]);

        return $ctr;
    }

    public function getContent($contenttype, Request $request)
    {

        $this->config->setCurrentRequest($request);
        $this->APIHelper->fixBoltStorageRequest();

        if (!array_key_exists($contenttype, $this->config->getContentTypes())) {
            return new JsonResponse([
                'detail' => "Contenttype with name [$contenttype] not found."
            ]);
        }

        $options = [];
        if ($limit = $request->get('page')) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }

        if ($page = $request->get('limit')) {
            $page = intval($page);
            if ($page >= 1) {
                $options['page'] = $page;
            }
        }
        if ($order = $request->get('sort')) {
            $options['order'] = $order;
        }

        // Enable pagination
        $options['paging'] = true;
        $pager  = [];
        $where  = [];

        $allFields = $this->APIHelper->getAllFieldNames($contenttype);
        $fields = $this->APIHelper->getFields($contenttype, $allFields, 'list-fields');

        // Use the `where-clause` defined in the contenttype config.
        if (isset($this->config->getContentTypes()[$contenttype]['where-clause'])) {
            $where = $this->config->getContentTypes()['contenttypes'][$contenttype]['where-clause'];
        }

        // Handle $filter[], this modifies the $where[] clause.
        if ($filters = $request->get('filter')) {
            foreach($filters as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return new JsonResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contenttype]."
                    ]);
                }
                // A bit crude for now.
                $where[$key] = str_replace(',', ' || ', $value);
            }
        }

        // Handle $contains[], this modifies the $where[] clause to search using Like.
        if ($contains = $request->get('contains')) {
            foreach ($contains as $key => $value) {
                if (!in_array($key, $allFields)) {
                    return new JsonResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contenttype]."
                    ]);
                }

                $values = explode(",", $value);

                foreach ($values as $i => $item) {
                    $values[$i] = '%' . $item .'%';
                }

                $where[$key] = implode(' || ', $values);
            }
        }

        // If `returnsingle` is not set to false, then a single result will not
        // result in an array.
        $where['returnsingle'] = false;
        $items = $this->app['storage']->getContent($contenttype, $options, $pager, $where);

        // If we don't have any items, this can mean one of two things: either
        // the contenttype does not exist (in which case we'll get a non-array
        // response), or it exists, but no content has been added yet.

        if (!is_array($items)) {
            return new JsonResponse([
                'detail' => "Configuration error: [$contenttype] is configured as a JSON end-point, but doesn't exist as a contenttype."
            ]);
        }

        if (empty($items)) {
            $items = [];
        }

        $items = array_values($items);

        // Handle "include" and fetch related relationships in current query.
        try {
            $included = $this->APIHelper->fetchIncludedContent($contenttype, $items);
        } catch(\Exception $e) {
            return new JsonResponse([
                'detail' => $e->getMessage()
            ]);
        }

        foreach($items as $key => $item) {
            $items[$key] = $this->APIHelper->cleanItem($item, $fields);
        }

        $response = [
            'links' => $this->APIHelper->makeLinks($contenttype, $pager['current'], intval($pager['totalpages']), $limit),
            'meta' => [
                "count" => count($items),
                "total" => intval($pager['count'])
            ],
            'data' => $items,
        ];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        return new JsonResponse($response);
    }

}