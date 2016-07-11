<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

class Taxonomy
{

    /** @var \Bolt\Storage\Collection\Taxonomy[] $taxonomy */
    protected $taxonomy;

    public function __construct(\Bolt\Storage\Collection\Taxonomy $taxonomy)
    {
        $this->taxonomy = $taxonomy;
    }

    public function parseTaxonomies()
    {
        foreach ($this->taxonomy as $taxonomy) {
            $type = $taxonomy->getTaxonomytype();
            $slug = $taxonomy->getSlug();
            $route = '/' . $type . '/' . $slug;
            $attributes['taxonomy'][$type][$route] = $taxonomy->getName();
        }
    }
}
