<?php
namespace JSONAPI\Config;


use Bolt\Application;
use Symfony\Component\HttpFoundation\Request;

class Config
{

    /**
     * @var string
     */
    private $base = "/json";

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var array
     */
    private $contentTypes;

    /**
     * @var array
     */
    private $replacements;

    /**
     * @var string
     */
    private $paginationNumberKey;

    /**
     * @var Request
     */
    private $currentRequest;


    public function __construct($config, Application $app)
    {
        if(isset($config['base'])) {
            $this->base = $config['base'];
        }

        $this->setBasePath($app['paths']['canonical'] . $this->base);
        $this->setContentTypes($config['contenttypes']);
        $this->setReplacements($config['replacements']);
        $this->setPaginationNumberKey("page");
    }

    /**
     * @return string
     */
    public function getBase()
    {
        return $this->base;
    }

    /**
     * @param string $base
     */
    public function setBase($base)
    {
        $this->base = $base;
    }

    /**
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * @param string $basePath
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @return array
     */
    public function getContentTypes()
    {
        return $this->contentTypes;
    }

    /**
     * @param array $contentTypes
     */
    public function setContentTypes($contentTypes)
    {
        $this->contentTypes = $contentTypes;
    }

    /**
     * @return Request
     */
    public function getCurrentRequest()
    {
        return $this->currentRequest;
    }

    /**
     * @param Request $currentRequest
     */
    public function setCurrentRequest($currentRequest)
    {
        $this->currentRequest = $currentRequest;
    }

    /**
     * @return array
     */
    public function getReplacements()
    {
        return $this->replacements;
    }

    /**
     * @param array $replacements
     */
    public function setReplacements($replacements)
    {
        $this->replacements = $replacements;
    }

    /**
     * @return string
     */
    public function getPaginationNumberKey()
    {
        return $this->paginationNumberKey;
    }

    /**
     * @param string $paginationNumberKey
     */
    public function setPaginationNumberKey($paginationNumberKey)
    {
        $this->paginationNumberKey = $paginationNumberKey;
    }
}