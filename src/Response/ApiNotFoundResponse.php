<?php

namespace Bolt\Extension\Bolt\JsonApi\Response;

use Bolt\Extension\Bolt\JsonApi\Config\Config;

/**
 * Class ApiNotFoundResponse
 * @package Bolt\Extension\Bolt\JsonApi\Response
 */
class ApiNotFoundResponse extends ApiErrorResponse
{
    /**
     * ApiNotFoundResponse constructor.
     * @param array $data
     * @param Config $config
     */
    public function __construct(array $data, Config $config)
    {
        parent::__construct('400', 'Invalid Request', $data, $config);
    }
}