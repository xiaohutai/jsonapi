<?php

namespace Bolt\Extension\Bolt\JsonApi\Response;

use Bolt\Extension\Bolt\JsonApi\Config\Config;

/**
 * Class ApiErrorResponse
 * @package Bolt\Extension\Bolt\JsonApi\Response
 */
class ApiErrorResponse extends ApiResponse
{

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $title;

    /**
     * ApiErrorResponse constructor.
     * @param string $status
     * @param string $title
     * @param array $data
     * @param Config $config
     */
    public function __construct($status, $title, array $data, Config $config)
    {
        $this->status = $status;
        $this->title = $title;

        parent::__construct($data, $config);
    }

    /**
     * @param array $content
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function setContent($content)
    {
        $content['status'] = $this->status;
        $content['title'] = $this->title;

        return parent::setContent([
            'errors' => $content
        ]);
    }
}