<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

use Bolt\Configuration\ResourceManager;
use Bolt\Extension\Bolt\JsonApi\Config\Config;

class File extends AbstractType
{
    /** @var ResourceManager $resourceManager */
    protected $resourceManager;

    /** @var Config $config */
    protected $config;

    protected $fieldType;

    /**
     * File constructor.
     * @param $type
     * @param $value
     * @param ResourceManager $resourceManager
     * @param Config $config
     */
    public function __construct(
        $type,
        $value,
        ResourceManager $resourceManager,
        Config $config
    ) {
        parent::__construct($type, $value);
        $this->resourceManager = $resourceManager;
        $this->config = $config;
    }

    public function render()
    {
        $values = [];

        if ($this->getFieldType() == 'imagelist') {
            foreach ($this->getValue() as &$image) {
                $image['url'] = $this->makeAbsoluteLinkToResource($image['filename']);
                $image['thumbnail'] = $this->makeAbsoluteLinkToThumbnail($image['filename']);
                $values[] = $image;
            }
        }


        if ($this->getFieldType() == 'filelist') {
            foreach ($this->getValue() as &$file) {
                $file['url'] = $this->makeAbsoluteLinkToResource($file['filename']);
                $values[] = $file;
            }
        }

        if ($this->getFieldType() === 'image') {
            $values = $this->getValue();
            $values['url'] = $this->makeAbsoluteLinkToResource($values['file']);
            $values['thumbnail'] = $this->makeAbsoluteLinkToThumbnail($values['file']);
        }

        if ($this->getFieldType() === 'file') {
            $values['file'] = $this->getValue();
            $values['url'] = $this->makeAbsoluteLinkToResource($values['file']);
        }

        $this->setValue($values);

        return $this->getValue();
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function makeAbsoluteLinkToResource($filename = '')
    {
        return sprintf(
            '%s%s%s',
            $this->resourceManager->getUrl('hosturl'),
            $this->resourceManager->getUrl('files'),
            $filename
        );
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function makeAbsoluteLinkToThumbnail($filename = '')
    {
        return sprintf(
            '%s/thumbs/%sx%s/%s',
            $this->resourceManager->getUrl('hosturl'),
            $this->config->getThumbnail()['width'],
            $this->config->getThumbnail()['height'],
            $filename
        );
    }

    /**
     * @return mixed
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }

    /**
     * @param mixed $fieldType
     * @return File
     */
    public function setFieldType($fieldType)
    {
        $this->fieldType = $fieldType;
        return $this;
    }
}
