<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Extension\Bolt\JsonApi\Response\ApiInvalidRequestResponse;
use Bolt\Extension\Bolt\JsonApi\Response\ApiNotFoundResponse;
use Symfony\Component\HttpFoundation\Request;

class JSONAPIConverter
{
    const DEFAULT_PAGE_SIZE = 10;
    const DEFAULT_PAGE_NUMBER = 1;
    const DEFAULT_ORDER = 'id';

    /** @var int $limit */
    protected $limit;

    /** @var int $page */
    protected $page;

    /** @var string $order */
    protected $order;

    /** @var array $filters */
    protected $filters;

    /** @var array $contains */
    protected $contains;

    /** @var array $includes */
    protected $includes;

    /** @var APIHelper $APIHelper */
    protected $APIHelper;

    /** @var Config $config */
    protected $config;

    public function __construct(APIHelper $APIHelper, Config $config)
    {
        $this->APIHelper = $APIHelper;
        $this->config = $config;
    }

    /**
     * @param $converter
     * @param Request $request
     * @return $this
     */
    public function convert($converter, Request $request)
    {
        $contentType = $request->attributes->get('contentType');

        if (! $this->isValidContentType($contentType)) {
            return new ApiNotFoundResponse([
                'detail' => "Contenttype with name [$contentType] not found."
            ], $this->config);
        }

        $limit = intval($request->query->get('page[size]', self::DEFAULT_PAGE_SIZE, true));
        $this->limit = $limit >= 1 ? $limit : self::DEFAULT_PAGE_SIZE;

        $page = intval($request->query->get('page[number]', self::DEFAULT_PAGE_NUMBER, true));
        $this->page = $page >= 1 ? $page : self::DEFAULT_PAGE_NUMBER;

        $this->order = $request->get('sort', self::DEFAULT_ORDER);

        // Handle $filter[], this modifies the $where[] clause.
        if ($filters = $request->get('filter')) {
            foreach ($filters as $key => $value) {
                if (! $this->isValidField($key, $contentType)) {
                    return new ApiInvalidRequestResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contentType]."
                    ], $this->config);
                }
                $this->filters[$key] = str_replace(',', ' || ', $value);
            }
        }

        // Handle $contains[], this modifies the $where[] clause to search using Like.
        if ($contains = $request->get('contains')) {
            foreach ($contains as $key => $value) {
                if (! $this->isValidField($key, $contentType)) {
                    return new ApiInvalidRequestResponse([
                        'detail' => "Parameter [$key] does not exist for contenttype with name [$contentType]."
                    ], $this->config);
                }

                $values = explode(',', $value);
                $newValues = [];

                foreach ($values as $index => $value) {
                    $newValues[$index] = '%' . $value . '%';
                }

                $this->contains[$key] = implode(' || ', $newValues);

            }
        }

        if ($includes = $request->get('include')) {
            $includes = explode(',', $includes);
            foreach ($includes as $ct) {
                if (! $this->isValidContentType($ct)) {
                    throw new \Exception("Content type with name [$ct] requested in include not found.");
                }
                $this->includes[] = $ct;
            }
        }

        return $this;

    }

    protected function isValidContentType($contentType)
    {
        return array_key_exists($contentType, $this->config->getContentTypes());
    }

    protected function isValidField($key, $contentType)
    {
        $allFields = $this->APIHelper->getAllFieldNames($contentType);

        return in_array($key, $allFields);
    }

    /**
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param mixed $limit
     * @return JSONAPIConverter
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param mixed $page
     * @return JSONAPIConverter
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param mixed $order
     * @return JSONAPIConverter
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFilters()
    {
        return ($this->filters ? $this->filters : []);
    }

    /**
     * @param mixed $filters
     * @return JSONAPIConverter
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getContains()
    {
        return ($this->contains ? $this->contains : []);
    }

    /**
     * @param mixed $contains
     * @return JSONAPIConverter
     */
    public function setContains($contains)
    {
        $this->contains = $contains;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIncludes()
    {
        return $this->includes;
    }

    /**
     * @param mixed $includes
     * @return JSONAPIConverter
     */
    public function setIncludes($includes)
    {
        $this->includes = $includes;
        return $this;
    }


}
