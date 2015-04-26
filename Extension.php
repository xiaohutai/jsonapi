<?php
/**
 * JSONAccess extension for Bolt.
 *
 * @author Tobias Dammers <tobias@twokings.nl>
 */

namespace JSONAccess;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Extension extends \Bolt\BaseExtension
{

    public function getName()
    {
        return "JSON Access";
    }

    public function initialize()
    {
        $this->app->get("/json/{contenttype}", array($this, 'json_list'))
                  ->bind('json_list');
        $this->app->get("/json/{contenttype}/{slug}/{relatedContenttype}", array($this, 'json'))
                  ->value('relatedContenttype', null)
                  ->assert('slug', '[a-zA-Z0-9_\-]+')
                  ->bind('json');

    }

    public function json_list(Request $request, $contenttype)
    {
        $this->request = $request;

        if (!array_key_exists($contenttype, $this->config['contenttypes'])) {
            return $this->app->abort(404, 'Not found');
        }
        $options = array();
        if ($limit = $request->get('limit')) {
            $limit = intval($limit);
            if ($limit >= 1) {
                $options['limit'] = $limit;
            }
        }
        if ($page = $request->get('page')) {
            $page = intval($page);
            if ($page >= 1) {
                $options['page'] = $page;
            }
        }
        if ($order = $request->get('order')) {
            if (!preg_match('/^([a-zA-Z][a-zA-Z0-9_\\-]*)\\s*(ASC|DESC)?$/', $order, $matches)) {
                return $this->app->abort(400, 'Invalid request');
            }
            $options['order'] = $order;
        }

        // Enable pagination
        $options['paging'] = true;
        $pager = [];
        $where = [];

        // Use the where clause defined in the contenttype config
        if (isset($this->config['contenttypes'][$contenttype]['where-clause'])) {
            $where = $this->config['contenttypes'][$contenttype]['where-clause'];
        }

        $items = $this->app['storage']->getContent($contenttype, $options, $pager, $where);

        // If we don't have any items, this can mean one of two things: either
        // the content type does not exist (in which case we'll get a non-array
        // response), or it exists, but no content has been added yet.
        if (!is_array($items)) {
            throw new \Exception("Configuration error: $contenttype is configured as a JSON end-point, but doesn't exist as a content type.");
        }
        if (empty($items)) {
            $items = array();
        }

        $items = array_values($items);
        $items = array_map(array($this, 'clean_list_item'), $items);

        return $this->response(array($contenttype => $items));

    }

    public function json(Request $request, $contenttype, $slug, $relatedContenttype)
    {
        $this->request = $request;

        if (!array_key_exists($contenttype, $this->config['contenttypes'])) {
            return $this->app->abort(404, 'Not found');
        }

        $item = $this->app['storage']->getContent("$contenttype/$slug");
        if (!$item) {
            return $this->app->abort(404, 'Not found');
        }

        // If a related entity name is given, we fetch its content instead
        if ($relatedContenttype !== null)
        {
            $items = $item->related($relatedContenttype);
            if (!$items) {
                return $this->app->abort(404, 'Not found');
            }
            $items = array_map(array($this, 'clean_list_item'), $items);
            $response = $this->response(array($relatedContenttype => $items));

        } else {

            $values = $this->clean_full_item($item);
            $response = $this->response($values);
        }

        return $response;
    }

    private function clean_item($item, $type = 'list-fields')
    {
        $contenttype = $item->contenttype['slug'];
        if (isset($this->config['contenttypes'][$contenttype][$type])) {
            $fields = $this->config['contenttypes'][$contenttype][$type];
        }
        else {
            $fields = array_keys($item->contenttype['fields']);
        }
        // Always include the ID in the set of fields
        array_unshift($fields, 'id');
        $fields = array_unique($fields);
        $values = array();
        foreach ($fields as $key => $field) {
            $values[$field] = $item->values[$field];
        }

        // Check if we have image or file fields present. If so, see if we need to
        // use the full URL's for these.
        foreach($item->contenttype['fields'] as $key => $field) {
            if (($field['type'] == 'image' || $field['type'] == 'file') && isset($values[$key])) {
                $values[$key]['url'] = sprintf('%s%s%s',
                    $this->app['paths']['canonical'],
                    $this->app['paths']['files'],
                    $values[$key]['file']
                    );
            }
            if ($field['type'] == 'image' && isset($values[$key]) && is_array($this->config['thumbnail'])) {
                // dump($this->app['paths']);
                $values[$key]['thumbnail'] = sprintf('%s/thumbs/%sx%s/%s',
                    $this->app['paths']['canonical'],
                    $this->config['thumbnail']['width'],
                    $this->config['thumbnail']['height'],
                    $values[$key]['file']
                    );
            }

        }

        return $values;

    }

    private function clean_list_item($item)
    {
        return $this->clean_item($item, 'list-fields');
    }

    private function clean_full_item($item)
    {
        return $this->clean_item($item, 'item-fields');
    }

    private function response($array)
    {

        $json = json_encode($array, JSON_PRETTY_PRINT);
        // $json = json_encode($array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);

        $response = new Response($json, 201);

        if (!empty($this->config['headers']) && is_array($this->config['headers'])) {
            // dump($this->config['headers']);
            foreach ($this->config['headers'] as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        if ($callback = $this->request->get('callback')) {
            $response->setCallback($callback);
        }

        return $response;

    }


}

