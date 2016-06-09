<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterFactory;
use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterInterface;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiInvalidRequestException;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiInvalidRequestResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Bolt\Storage\Mapping\MetadataDriver;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Request;

class JSONAPIConverter
{
    /** @var APIHelper $APIHelper */
    protected $APIHelper;

    /** @var Config $config */
    protected $config;

    /** @var MetadataDriver $metadata */
    protected $metadata;

    public function __construct(APIHelper $APIHelper, Config $config, MetadataDriver $metadata)
    {
        $this->metadata = $metadata;
        $this->APIHelper = $APIHelper;
        $this->config = $config;
    }

    /**
     * @param $converter
     * @param Request $request
     * @return Collection|ParameterInterface[]
     */
    public function grabParameters($converter, Request $request)
    {
        $parameters = [];

        //Get content type
        $parameters['contentType'] = $request->attributes->get('contentType');

        $parameters['page']['size'] = $request->query->get('page[size]', false, true);
        $parameters['page']['number'] = $request->query->get('page[number]', false, true);
        $parameters['sort'] = $request->query->get('sort', false);
        $parameters['filters'] = $request->query->get('filter', false);
        $parameters['contains'] = $request->query->get('contains', false);
        $parameters['includes'] = $request->query->get('include', false);
        $parameters['fields'] = $request->query->get('fields', false);
        $parameters['search'] = $request->query->get('q', false);

        $parameterCollection = ParameterFactory::build($parameters, $this->config, $this->metadata);

        return $parameterCollection;
    }
}
