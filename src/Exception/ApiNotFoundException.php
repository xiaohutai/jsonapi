<?php


namespace Bolt\Extension\Bolt\JsonApi\Exception;

use Symfony\Component\HttpFoundation\Response;

class ApiNotFoundException extends ApiException
{
    /**
     * Constructor.
     *
     * @param int|string     $statusCode  The internal statusCode
     * @param string     $message  The internal exception message
     */
    public function __construct(
        $message = null,
        $statusCode = Response::HTTP_NOT_FOUND
    ) {
        parent::__construct($message, $statusCode);
    }
}
