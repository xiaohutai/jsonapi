<?php

namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Config;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class RootAction
 *
 * @package Bolt\Extension\Bolt\JsonApi\Action
 */
class RootAction
{
    /** @var Config $config */
    protected $boltConfig;

    /** @var \Bolt\Extension\Bolt\JsonApi\Config\Config $extensionConfig */
    protected $extensionConfig;

    /** @var string $boltVersion */
    protected $boltVersion;

    /** @var string $jsonAPIVersion */
    protected $jsonAPIVersion;

    /**
     * MenuAction constructor.
     *
     * @param Config                                     $boltConfig
     * @param \Bolt\Extension\Bolt\JsonApi\Config\Config $extensionConfig
     * @param string                                     $boltVersion
     * @param string                                     $jsonAPIVersion
     */
    public function __construct(Config $boltConfig, \Bolt\Extension\Bolt\JsonApi\Config\Config $extensionConfig, $boltVersion, $jsonAPIVersion)
    {
        $this->boltConfig = $boltConfig;
        $this->extensionConfig = $extensionConfig;
        $this->boltVersion = $boltVersion;
        $this->jsonAPIVersion = $jsonAPIVersion;
    }

    /**
     * @param Request $request
     *
     * @return ApiResponse
     */
    public function handle(Request $request)
    {
        $data = 'API is active.';
        $debug = $this->boltConfig->getConfig()['general']['debug'];

        if ($debug) {
            $data = [
                'versions' => [
                    'bolt' => $this->boltVersion,
                    'jsonapi' => $this->jsonAPIVersion
                ]
            ];
        }

        return new ApiResponse([
            'data' => $data,
        ], $this->extensionConfig);
    }
}
