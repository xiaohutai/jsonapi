<?php

namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Config;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Class MenuAction
 *
 * @package Bolt\Extension\Bolt\JsonApi\Action
 */
class MenuAction
{
    /** @var Config $config */
    protected $boltConfig;

    /** @var \Bolt\Extension\Bolt\JsonApi\Config\Config $extensionConfig */
    protected $extensionConfig;

    /**
     * MenuAction constructor.
     *
     * @param Config                                     $boltConfig
     * @param \Bolt\Extension\Bolt\JsonApi\Config\Config $extensionConfig
     */
    public function __construct(Config $boltConfig, \Bolt\Extension\Bolt\JsonApi\Config\Config $extensionConfig)
    {
        $this->boltConfig = $boltConfig;
        $this->extensionConfig = $extensionConfig;
    }

    /**
     * @param Request $request
     *
     * @return ApiResponse
     */
    public function handle(Request $request)
    {
        if ($name = $request->get('q', '')) {
            $name = "/$name";
        }

        $menu = $this->boltConfig->get('menu' . $name, false);

        if (! $menu) {
            throw new ApiNotFoundException(
                "Menu with name [$name] not found."
            );
        }

        // See https://github.com/xiaohutai/jsonapi/issues/52
        // This part keeps BC for now. In a future major release, remove the
        // non-jsonapi response.
        $accept = AcceptHeader::fromString($request->headers->get('Accept'));
        if ($accept->has('application/vnd.api+json')) {
            // [TODO] Make a jsonapi proper response;
            // What's a proper thing to return??
        }

        // if ($accept->has('application/json')) {
        $response = new ApiResponse([
            'links' => [
                'self' => $this->extensionConfig->getBasePath() . '/menu' . ( $name ? "?q=$name" : '' ),
            ],
            'data' => $menu,
        ], $this->extensionConfig);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
        // }
    }
}
