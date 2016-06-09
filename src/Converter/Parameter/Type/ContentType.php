<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;

class ContentType extends AbstractParameter
{

    /** @var string $contentType */
    protected $contentType;

    public function convertRequest()
    {
        $contentType = $this->values;

        if (! $this->isValidContentType($contentType)) {
            throw new ApiNotFoundException("Contenttype with name [$contentType] not found.");
        }

        //Get contentType
        $this->contentType = $contentType;

        return $this;
    }

    public function findConfigValues()
    {

    }

    public function getParameter()
    {
        return $this->getContentType();
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     * @return ContentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;

        return $this;
    }
}
