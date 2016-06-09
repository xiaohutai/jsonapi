<?php


namespace Bolt\Extension\Bolt\JsonApi\Helpers;

use Bolt\Extension\Bolt\JsonApi\Config\Config;
use Bolt\Helpers\Arr;

class DataLinks
{

    /** @var Config $config */
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns the values for the "links" object in a listing response.
     *
     * @param string $contentType The name of the contenttype.
     * @param int $currentPage The current page number.
     * @param int $totalPages The total number of pages.
     * @param int $pageSize The number of items per page.
     * @return mixed[] An array with URLs for the current page and related
     *                 pagination pages.
     */
    public function makeLinks($contentType, $currentPage, $totalPages, $pageSize)
    {
        $basePath = $this->config->getBasePath();
        $basePathContentType = $basePath . '/' . $contentType;
        $prevPage = max($currentPage - 1, 1);
        $nextPage = min($currentPage + 1, $totalPages);
        $firstPage = 1;
        $pagination = $firstPage != $totalPages;

        $links = [];
        $defaultQueryString = $this->makeQueryParameters();

        $params = $pagination
            ? $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $currentPage])
            : $defaultQueryString;

        $links["self"] = $basePathContentType.$params;

        // The following links only exists if a query was made using pagination.
        if ($currentPage != $firstPage) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $firstPage]);
            $links["first"] = $basePathContentType.$params;
        }
        if ($currentPage != $totalPages) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $totalPages]);
            $links["last"] = $basePathContentType.$params;
        }
        if ($currentPage != $prevPage) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $prevPage]);
            $links["prev"] = $basePathContentType.$params;
        }
        if ($currentPage != $nextPage) {
            $params = $this->makeQueryParameters([$this->config->getPaginationNumberKey() => $nextPage]);
            $links["next"] = $basePathContentType.$params;
        }

        return $links;
    }

    /**
     * Make related links for a singular item.
     *
     * @param \Bolt\Storage\Entity\Content $item
     * @return mixed[] An array with URLs for the relationships.
     */
    public function makeRelatedLinks($item)
    {
        $related = [];
        $contentType = (string)$item->getContenttype();

        if (count($item->getRelation()) > 0) {
            foreach ($item->getRelation() as $relatedType) {
                $id = $relatedType->getId();
                $fromType = $relatedType->getFromContenttype();

                $related[$fromType] = [
                    'href' => $this->config->getBasePath()."/$contentType/$id/$fromType",
                    'meta' => [
                        'count' => 1
                    ]
                ];
            }
        }

        return $related;
    }

    /**
     * Make a new querystring while preserving current query parameters with the
     * option to override values.
     *
     * @param array $overrides A (key,value)-array with elements to override in
     *                         the current query string.
     * @param bool $buildQuery Returns a querystring if set to true, otherwise
     *                          returns the array with (key,value)-pairs.
     * @return mixed query parameters in either array or string form.
     *
     * @see \Bolt\Helpers\Arr::mergeRecursiveDistinct()
     */
    public function makeQueryParameters($overrides = [], $buildQuery = true)
    {
        //$queryParameters = $this->config->getCurrentRequest()->query->all();

        // todo: (optional) cleanup. There is a default set of fields we can
        //       expect using this Extension and jsonapi. Or we could ignore
        //       them like we already do.

        // Using Bolt's Helper Arr class for merging and overriding values.
        //$queryParameters = Arr::mergeRecursiveDistinct($queryParameters, $overrides);
        $queryParameters = $overrides;
        if ($buildQuery) {
            // No need to urlencode these, afaik.
            $queryString =  urldecode(http_build_query($queryParameters));
            if (!empty($queryString)) {
                $queryString = '?' . $queryString;
            }
            return $queryString;
        }
        return $queryParameters;
    }
}
