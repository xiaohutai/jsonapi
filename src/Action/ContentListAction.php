<?php


namespace Bolt\Extension\Bolt\JsonApi\Action;

use Bolt\Extension\Bolt\JsonApi\Converter\Parameter\ParameterCollection;
use Bolt\Extension\Bolt\JsonApi\Helpers\APIHelper;
use Bolt\Storage\Query\Query;

class ContentListAction
{
    protected $query;

    protected $parameters;

    public function __construct(
        Query $query, 
        ParameterCollection $parameters,
        APIHelper $APIHelper
    ) {
    }

    public function handle()
    {
        
    }

    protected function fetchIncludes()
    {
        foreach ($includes as $include) {
            //Loop through all results
            foreach ($results as $key => $item) {
                $ctAllFields = $this->APIHelper->getAllFieldNames($include);
                $ctFields = $this->APIHelper->getFields($include, $ctAllFields, 'list-fields');
                //Loop through all relationships
                foreach ($item->relation[$include] as $related) {
                    $included[$key] = $this->APIHelper->cleanItem($related, $ctFields);
                }
            }
        }
    }
}

