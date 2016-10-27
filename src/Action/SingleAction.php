<?php


namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Exception\ApiNotFoundException;
use Bolt\Extension\Bolt\JsonApi\Response\ApiResponse;
use Bolt\Storage\Query\QueryResultset;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SingleAction
 * @package Bolt\Extension\Bolt\JsonApi\Action
 */
class SingleAction extends FetchAction
{
    /**
     * @param $contentType
     * @param $slug
     * @param $relatedContentType
     * @param Request $request
     * @param ParameterCollection $parameters
     * @return array|ApiResponse
     */
    public function handle($contentType, $slug, $relatedContentType, Request $request, ParameterCollection $parameters)
    {
        $additionalParameters = ['returnsingle' => true];

        //Check to see if ID or SLUG
        if (is_numeric($slug)) {
            $additionalParameters['id'] = $slug;
        } else {
            $additionalParameters['slug'] = $slug;
        }

        $queryParameters = array_merge($parameters->getQueryParameters(), $additionalParameters);

        /** @var QueryResultset $results */
        $results = $this->query->getContent($contentType, $queryParameters);

        $this->throwErrorOnNoResults($results, "No [$contentType] found with id/slug: [$slug].");

        if ($relatedContentType !== null) {
            $relatedItemsTotal = $results->getRelation()->getField($relatedContentType)->count();
            if ($relatedItemsTotal <= 0) {
                throw new ApiNotFoundException(
                    "No related items of type [$relatedContentType] found for [$contentType] with id/slug: [$slug]."
                );
            }

            //$allFields = $this->APIHelper->getAllFieldNames($relatedContentType);
            //$fields = $this->APIHelper->getFields($relatedContentType, $allFields, 'list-fields');
            // @todo item-fields INSTEAD of list-items
            $fields = $parameters->get('fields')->getFields();

            foreach ($results->relation[$relatedContentType] as $item) {
                $items[] = $this->parser->parseItem($item, $fields);
            }

            $response = new ApiResponse([
                'links' => [
                    'self' => $this->config->getBasePath() .
                        "/$contentType/$slug/$relatedContentType" .
                        $this->dataLinks->makeQueryParameters($request->query->all())
                ],
                'meta' => [
                    "count" => count($items),
                    "total" => count($items)
                ],
                'data' => $items
            ], $this->config);
        } else {
            //$allFields = $this->APIHelper->getAllFieldNames($contentType);
            //$fields = $this->APIHelper->getFields($contentType, $allFields, 'item-fields');
            $fields = $parameters->get('fields')->getFields();
            $values = $this->parser->parseItem($results, $fields);
            //@todo get previous and next link
            $prev = $results->previous();
            $next = $results->next();

            $defaultQueryString = $this->dataLinks->makeQueryParameters($request->query->all());
            $links = [
                'self' => $values['links']['self'] . $defaultQueryString
            ];

            // optional: This adds additional relationships links in the root
            //           variable 'links'.
            $related = $this->dataLinks->makeRelatedLinks($results);
            foreach ($related as $ct => $link) {
                $links[$ct] = $link;
            }

            $this->fetchIncludes(
                $parameters->getParametersByType('includes'),
                $results,
                $parameters
            );

            if ($prev) {
                $links['prev'] = sprintf(
                    '%s/%s/%d%s',
                    $this->config->getBasePath(),
                    $contentType,
                    $prev->values['id'],
                    $defaultQueryString
                );
            }
            if ($next) {
                $links['next'] = sprintf(
                    '%s/%s/%d%s',
                    $this->config->getBasePath(),
                    $contentType,
                    $next->values['id'],
                    $defaultQueryString
                );
            }

            $response = [
                'links' => $links,
                'data' => $values,
            ];

            if (!empty($included)) {
                $response['included'] = $included;
            }

            $response = new ApiResponse($response, $this->config);
        }

        return $response;
    }
}
