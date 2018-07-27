<?php

namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Config as BoltConfig;
use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;
use Bolt\Extension\Bolt\JsonApi\Helpers\DataLinks;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MenuAction
 *
 * @package Bolt\Extension\Bolt\JsonApi\Action
 */
class MenuAction
{
    /** @var BoltConfig $config */
    protected $boltConfig;

    /** @var Config $extensionConfig */
    protected $extensionConfig;

    /** @var DataLinks $dataLinks */
    protected $dataLinks;

    /**
     * MenuAction constructor.
     *
     * @param BoltConfig $boltConfig
     * @param Config     $extensionConfig
     * @param DataLinks  $dataLinks
     */
    public function __construct(BoltConfig $boltConfig, Config $extensionConfig, DataLinks $dataLinks)
    {
        $this->boltConfig = $boltConfig;
        $this->extensionConfig = $extensionConfig;
        $this->dataLinks = $dataLinks;
    }

    /**
     * @param Request $request
     *
     * @return ApiResponse
     */
    public function handle(Request $request)
    {
        if ($name = $request->get('q', '')) {
            $data = $this->singleMenu($name);
        } else {
            $data = $this->allMenus();
        }

        return new ApiResponse([
            'links' => [
                'self' => $this->extensionConfig->getBasePath() .
                    "/menu" .
                    $this->dataLinks->makeQueryParameters($request->query->all())
            ],
            'data' => $data,
        ], $this->extensionConfig);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    private function singleMenu($name)
    {
        $menu = $this->boltConfig->get('menu/' . $name, false);

        if (! $menu) {
            throw new ApiNotFoundException(
                "Menu with name [$name] not found."
            );
        }

        return $this->parseMenu($name, $menu);
    }

    /**
     * @return array
     */
    private function allMenus()
    {
        $menus = $this->boltConfig->get('menu', false);

        return array_map(
            [$this, 'parseMenu'],
            array_keys($menus),
            array_values($menus)
        );
    }

    /**
     * @param string $name
     * @param array  $menu
     *
     * @return array
     */
    private function parseMenu($name, $menu)
    {
        return [
            'id' => $name,
            'type' => 'menu',
            'attributes' => [
                'items' => $menu,
            ],
            'links' => [
                'self' => $this->extensionConfig->getBasePath() . "/menu?q=" . $name
            ]
        ];
    }
}
