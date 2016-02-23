<?php

namespace Bolt\Extension\Bolt\JsonApi\Response;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Symfony\Component\HttpFoundation\Response;

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
        parent::__construct(Response::HTTP_BAD_REQUEST,
            Response::$statusTexts[Response::HTTP_BAD_REQUEST], $data, $config);
    }
}