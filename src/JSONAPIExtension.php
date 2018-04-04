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

use Bolt\Controller\Zone;
use Bolt\Extension\Bolt\JsonApi\Controllers\ContentController;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiException;
use Bolt\Extension\Bolt\JsonApi\Provider\APIProvider;
use Bolt\Extension\Bolt\JsonApi\Response\ApiErrorResponse;
use Bolt\Extension\SimpleExtension;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 */
class JSONAPIExtension extends SimpleExtension
{
    /**
     * @return array
     */
    public function getServiceProviders()
    {
        return [
            $this,
            new APIProvider($this->getConfig()),
        ];
    }

    /**
     * @return array
     */
    protected function registerFrontendControllers()
    {
        $container = $this->getContainer();

        //You can't mount the same route twice, because it will be overwritten in the array.
        return [
            $container['jsonapi.config']->getBase() =>
                new ContentController($container['jsonapi.config']),
        ];
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        $parentEvents = parent::getSubscribedEvents();

        //Priority must be greater than 515 or otherwise skipped by Bolts Exception handler
        $localEvents = [
            KernelEvents::EXCEPTION => [
                ['error', 515],
            ],
            KernelEvents::CONTROLLER => [
                ['disableFrontend', 10]
            ]
        ];

        return $parentEvents + $localEvents;
    }

    public function disableFrontend(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        //$response = $event->getResponse();

        $container = $this->getContainer();

        //Get route name
        $routeName = $request->get('_route');

        //Check if request is to frontend
        if (Zone::isFrontend($request)) {
            //Check if we should disable frontend based upon the configuration
            if ($container['jsonapi.config']->isDisableFrontend()) {
                //Only disable frontend routes, don't disable json routes
                if (strpos($routeName, 'jsonapi') === false) {
                    $event->stopPropagation();
                    $event->setController(
                        function() {
                            return new Response('', 200);
                        }
                    );
                }
            }
        }
    }

    /**
     * Listener to handle all exceptions thrown of type ApiException. It converts
     * the exception into an ApiErrorResponse.
     *
     * @param GetResponseForExceptionEvent $response
     */
    public function error(GetResponseForExceptionEvent $response)
    {
        $exception = $response->getException();

        if ($exception instanceof ApiException) {
            $container = $this->getContainer();

            $response->setResponse(new ApiErrorResponse(
                $exception->getStatusCode(),
                Response::$statusTexts[$exception->getStatusCode()],
                ['details' => $exception->getMessage()],
                $container['jsonapi.config']
            ));
        }
    }
}
