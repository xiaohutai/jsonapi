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
        parent::__construct($data, $config);
        $this->status = $status;
        $this->title = $title;
    }

    public function setContent(array $content)
    {
        $data['status'] = $this->status;
        $data['title'] = $this->title;

        return parent::setContent([
            'errors' => $content
        ]);
    }
}