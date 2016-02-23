<?php

namespace Bolt\Extension\Bolt\JsonApi\Response;

use Bolt\Extension\Bolt\JsonApi\Config\Config;

/**
 * Class ApiInvalidRequestResponse
 * @package Bolt\Extension\Bolt\JsonApi\Response
 */
class ApiInvalidRequestResponse extends ApiErrorResponse
{
    /**
     * ApiInvalidRequestResponse constructor.
     * @param array $data
     * @param Config $config
     */
    public function __construct(array $data, Config $config)
    {
        parent::__construct('400', 'Invalid Request', $data, $config);
    }
}