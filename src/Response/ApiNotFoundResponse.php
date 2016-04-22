<?php

namespace Bolt\Extension\Bolt\JsonApi\Response;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Symfony\Component\HttpFoundation\Response;

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
        parent::__construct(Response::HTTP_NOT_FOUND,
            Response::$statusTexts[Response::HTTP_NOT_FOUND], $data, $config);
    }
}