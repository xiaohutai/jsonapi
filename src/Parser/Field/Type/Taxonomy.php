<?php


namespace Bolt\Extension\Bolt\JsonApi\Parser\Field\Type;

class Taxonomy extends AbstractType
{
    public function render()
    {
        /** @var \Bolt\Storage\Collection\Taxonomy $taxonomies */
        $taxonomies = $this->getValue();

        foreach ($taxonomies as $taxonomy) {
            $type = $taxonomy->getTaxonomytype();
            $slug = $taxonomy->getSlug();
            $route = '/' . $type . '/' . $slug;
            $attributes[$route] = $taxonomy->getName();
        }

        return $attributes;
    }

    public function isTaxonomy()
    {
        return true;
    }
}
