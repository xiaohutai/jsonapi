<?php

namespace Bolt\Extension\Bolt\JsonApi\Config;

use Bolt\Application;
use Symfony\Component\HttpFoundation\Request;

class Config
{
    /**
     * @var string
     */
    private $base = '/json';

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
     * @var
     */
    private $paginationSizeKey;

    /**
     * @var Request
     */
    private $currentRequest;

    /**
     * @var string
     */
    private $thumbnail;

    /**
     * @var string
     */
    private $dateIso;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var array
     */
    private $jsonOptions;

    /**
     * @var boolean
     */
    private $disableFrontend;

    /**
     * @var boolean
     */
    private $enableDisplayNames;

    /**
     * @param array       $config
     * @param Application $app
     */
    public function __construct($config, Application $app)
    {
        if (isset($config['base'])) {
            $this->base = $config['base'];
        }

        $this->setBasePath($app['paths']['hosturl'] . $this->base);
        $this->setContentTypes($config['contenttypes']);
        $this->setReplacements($config['replacements']);
        $this->setPaginationNumberKey('page[number]');
        $this->setPaginationSizeKey('limit');
        $this->setThumbnail($config['thumbnail']);
        $this->setDateIso($config['date-iso-8601']);
        $this->setHeaders($config['headers']);
        $this->setJsonOptions($config['jsonoptions']);

        $disablefrontend = isset($config['disablefrontend']) ? $config['disablefrontend'] : false;
        $this->setDisableFrontend($disablefrontend);

        $enableDisplayNames = isset($config['enabledisplaynames']) ? $config['enabledisplaynames'] : false;
        $this->setEnableDisplayNames($enableDisplayNames);
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

    /**
     * @return mixed
     */
    public function getPaginationSizeKey()
    {
        return $this->paginationSizeKey;
    }

    /**
     * @param mixed $paginationSizeKey
     */
    public function setPaginationSizeKey($paginationSizeKey)
    {
        $this->paginationSizeKey = $paginationSizeKey;
    }

    /**
     * @return string
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * @param string $thumbnail
     */
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;
    }

    /**
     * @return string
     */
    public function getDateIso()
    {
        return $this->dateIso;
    }

    /**
     * @param string $dateIso
     */
    public function setDateIso($dateIso)
    {
        $this->dateIso = $dateIso;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @return array
     */
    public function getJsonOptions()
    {
        return $this->jsonOptions;
    }

    /**
     * @param array $jsonOptions
     */
    public function setJsonOptions($jsonOptions)
    {
        $this->jsonOptions = $jsonOptions;
    }

    /**
     * @param $contentType
     *
     * @return array
     */
    public function getWhereClauses($contentType)
    {
        if (isset($this->contentTypes[$contentType]['where-clause'])) {
            return $this->contentTypes[$contentType]['where-clause'];
        }

        return [];
    }

    /**
     * @param $contentType
     *
     * @return array
     */
    public function getListFields($contentType)
    {
        if (isset($this->contentTypes[$contentType]['list-fields'])) {
            return $this->contentTypes[$contentType]['list-fields'];
        }

        return [];
    }

    /**
     * @param $contentType
     *
     * @return array
     */
    public function getItemFields($contentType)
    {
        if (isset($this->contentTypes[$contentType]['item-fields'])) {
            return $this->contentTypes[$contentType]['item-fields'];
        }

        return [];
    }

    /**
     * @param $contentType
     *
     * @return array
     */
    public function getAllowedFields($contentType)
    {
        if (isset($this->contentTypes[$contentType]['allowed-fields'])) {
            return $this->contentTypes[$contentType]['allowed-fields'];
        }

        return [];
    }

    /**
     * @return string
     */
    public function getSort($contentType)
    {
        if (isset($this->contentTypes[$contentType]['order'])) {
            return $this->contentTypes[$contentType]['order'];
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isDisableFrontend()
    {
        return $this->disableFrontend;
    }

    /**
     * @param bool $disableFrontend
     *
     * @return Config
     */
    public function setDisableFrontend($disableFrontend)
    {
        $this->disableFrontend = $disableFrontend;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnableDisplayNames()
    {
        return $this->enableDisplayNames;
    }

    /**
     * @param bool $enableDisplayNames
     *
     * @return Config
     */
    public function setEnableDisplayNames($enableDisplayNames)
    {
        $this->enableDisplayNames = $enableDisplayNames;

        return $this;
    }
}
