<?php

namespace Bolt\Extension\Bolt\JsonApi\Response;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ApiResponse
 * @package Bolt\Extension\Bolt\JsonApi\Response
 */
class ApiResponse extends Response
{
    /**
     * @var Config
     */
    private $config;

    /**
     * ApiResponse constructor.
     * @param array $data
     * @param Config $config
     */
    public function __construct(array $data, Config $config)
    {
        $this->config = $config;
        parent::__construct($data, Response::HTTP_OK, []);
        $this->setHeadersFromConfig();
    }

    /**
     * encodes the data and sets the json options from the config object
     *
     * @param array $content
     * @return Response
     */
    public function setContent($content)
    {

        if ($this->config->getJsonOptions()) {
            $json_encodeOptions = $this->config->getJsonOptions();
        } else {
            $json_encodeOptions = JSON_PRETTY_PRINT;
        }

        $this->setStatusCodeFromData($content);
        $json = json_encode($content, $json_encodeOptions);

        return parent::setContent($json);
    }


    /**
     * Adds the headers to the response based on the config
     */
    private function setHeadersFromConfig()
    {
        if (!empty($this->config->getHeaders()) && is_array($this->config->getHeaders())) {
            foreach ($this->config->getHeaders() as $header => $value) {
                $this->headers->set($header, $value);
            }
        }
    }


    /**
     * Checks the data for 'errors' key and sets a status code based on
     * @param array $data
     */
    private function setStatusCodeFromData(array $data)
    {
        if (isset($data['errors'])) {
            $status = isset($data['errors']['status']) ? $data['errors']['status'] : Response::HTTP_BAD_REQUEST;
            $this->setStatusCode($status);
        } else {
            $this->setStatusCode(Response::HTTP_OK);
        }
    }

}