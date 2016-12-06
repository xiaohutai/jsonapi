<?php

namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Config;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Request;

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

        return new ApiResponse([
            'data' => $menu,
        ], $this->extensionConfig);
    }
}
