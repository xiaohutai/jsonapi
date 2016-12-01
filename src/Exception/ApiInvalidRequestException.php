<?php

namespace Bolt\Extension\Bolt\JsonApi\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Class ApiInvalidRequestException
 *
 * @package Bolt\Extension\Bolt\JsonApi\Exception
 */
class ApiInvalidRequestException extends ApiException
{
    /**
     * Constructor.
     *
     * @param int|string $statusCode The internal statusCode
     * @param string     $message    The internal exception message (400)
     */
    public function __construct(
        $message = null,
        $statusCode = Response::HTTP_BAD_REQUEST
    ) {
        parent::__construct($message, $statusCode);
    }
}
