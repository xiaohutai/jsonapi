<?php

namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type;

/**
 * Class Fields
 *
 * @package Bolt\Extension\Bolt\JsonApi\Converter\Parameter\Type
 */
class Fields extends AbstractParameter
{
    /** @var array $fields */
    protected $fields;

    /**
     * Parameter example: fields[pages]=title,teaser
     *
     * @return $this
     */
    public function convertRequest()
    {
        $this->fields = [];

        if (! isset($this->contentType)) {
            $allContentTypes = array_keys($this->config->getContentTypes());
            foreach ($allContentTypes as $contentType) {
                $this->getFieldsForContentType($contentType);
            }
        } else {
            $this->getFieldsForContentType($this->contentType);
        }

        return $this;
    }

    protected function getFieldsForContentType($contentType)
    {
        if ($this->config->getAllowedFields($contentType)) {
            $allowedFields = $this->config->getAllowedFields($contentType);
        } else {
            $allowedFields = array_keys($this->getAllFieldNames($contentType));
        }

        if (isset($this->values[$contentType])) {
            $values = explode(',', $this->values[$contentType]);
            foreach ($values as $v) {
                if (in_array($v, $allowedFields)) {
                    $this->fields[$contentType] = $v;
                }
            }
        }

        // Default on the default/fallback fields defined in the config.
        if (empty($this->fields[$contentType])) {
            $this->fields[$contentType] = $allowedFields;
            if ($this->config->getListFields($contentType)) {
                $this->fields[$contentType] = $this->config->getListFields($contentType);
                // todo: do we need to filter these through 'allowed-fields'?
            }
        }
    }


    public function findConfigValues()
    {
    }

    /**
     * @return array
     */
    public function getParameter()
    {
        return $this->getFields();
    }

    /**
     * @return array
     */
    public function getFields($contentType = null)
    {
        if (! $contentType) {
            return $this->fields[$this->contentType];
        }

        return $this->fields[$contentType];
    }

    /**
     * @param array $fields
     *
     * @return Fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
