<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterInterface;
use Bolt\Storage\Mapping\MetadataDriver;

abstract class AbstractParameter implements ParameterInterface
{

    /** @var string $contentType */
    protected $contentType;

    protected $values;

    /** @var Config $config */
    protected $config;

    /** @var MetadataDriver $metadata */
    protected $metadata;

    public function __construct($contentType, $values, Config $config, MetadataDriver $metadata)
    {
        $this->contentType = $contentType;
        $this->values = $values;
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public static function initialize($contentType, $values, Config $config, MetadataDriver $metadata)
    {
        return new static($contentType, $values, $config, $metadata);
    }

    abstract public function convertRequest();

    abstract public function findConfigValues();

    abstract public function getParameter();

    /**
     * Returns all field names for the given contenttype.
     */
    protected function getAllFieldNames()
    {
        return $this->metadata->getClassMetadata($this->contentType)['fields'];
    }

    /**
     * @param $key
     * @return bool
     */
    protected function isValidField($key)
    {
        return array_key_exists($key, $this->getAllFieldNames());
    }

    /**
     * @param $ct
     * @return bool
     */
    protected function isValidContentType($ct)
    {
        return array_key_exists($ct, $this->config->getContentTypes());
    }
}

