<?php

namespace Bolt\Extension\Bolt\JsonApi\Exception;

use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class ApiException
 *
 * @package Bolt\Extension\Bolt\JsonApi\Exception
 */
class ApiException extends HttpException
{
    /**
     * Constructor.
     *
     * @param int|string $statusCode The internal statusCode
     * @param string     $message    The internal exception message
     * @param Exception  $previous   The previous exception
     * @param int        $code       The internal exception code
     */
    public function __construct(
        $message = null,
        $statusCode = null,
        Exception $previous = null,
        $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, [], $code);
    }
}
