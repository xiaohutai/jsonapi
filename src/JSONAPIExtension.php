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

use Bolt\Extension\Bolt\JsonApi\Controllers\ContentController;
use Bolt\Extension\Bolt\JsonApi\Controllers\MenuController;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiException;
use Bolt\Extension\Bolt\JsonApi\Provider\APIProvider;
use Bolt\Extension\Bolt\JsonApi\Response\ApiErrorResponse;
use Bolt\Extension\SimpleExtension;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This extension tries to return JSON responses according to the specifications
 * on jsonapi.org as much as possible. This extension is originally based on the
 * `bolt/jsonaccess` extension.
 */
class JSONAPIExtension extends SimpleExtension
{
    public function getServiceProviders()
    {
        return [
            $this,
            new APIProvider($this->getConfig())
        ];
    }

    protected function registerFrontendControllers()
    {
        $container = $this->getContainer();

        //You can't mount the same route twice, because it will be overwritten in the array.
        return [
            $container['jsonapi.config']->getBase() =>
                new ContentController($container['jsonapi.config']),
        ];
    }

    public static function getSubscribedEvents()
    {
        $parentEvents = parent::getSubscribedEvents();

        //Priority must be greater than 515 or otherwise skipped by Bolts Exception handler
        $localEvents = [
            KernelEvents::EXCEPTION => [
                ['error', 515],
            ],
        ];

        return $parentEvents + $localEvents;
    }

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
