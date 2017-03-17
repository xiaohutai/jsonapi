JSON API
========

Provides simple JSON access to Bolt's internal data structures. The specification
of the JSON responses are based on [JSON API](http://jsonapi.org/).

Enabling this extension adds routes to the application that serves content as
JSON rather than HTML. This route defaults to `/json`, so if, for example, you
have a contenttype named `entries`, then the entry with ID 1 is available
under `/json/entries/1`.

Configuration
-------------

In order to enable JSON serving for any contenttype, it has to be added to the
extension's configuration file, located at `app/config/extensions/jsonapi.bolt.yml`.

Note in particular that contenttypes that you don't mention in the
configuration file won't be served by the JSON API extension.

### Base URL

Overriding the `base` setting allows you to customize where the API is
accessible from. This defaults to `/json`.

```YML
base: '/json'
```

### Contenttypes

Only contenttypes listed under `contenttypes` in the config will be served; for
any other contenttype, the API will return 404 errors.

Besides errors, there are two types of responses you can receive, a `list` of
items or single `item`. You can customize the fields shown in the responses with
`list-fields` and `item-fields` respectively.

Both field names and taxonomy names are supported. If a field and a taxonomy
share the same names, both will be included. Taxonomy values are always under
the `taxonomy` property under `attributes`, whereas _normal_ fields can be found
directly under `attributes`.

To use the defaults for a contenttype, just leave its entry empty. This will
include all user-defined fields (fields and taxonomies in `contenttypes.yml`),
the ID and its contenttype in the listing, but not any of the base fields that
Bolt adds to all contenttypes, such as `datecreated`.

With JSON API, you can also request which fields are to be returned with
`?fields[<contenttype>]=<field1>,<field2>`. To limit the options, you can set
`allowed-fields` in order to filter these. At the moment, `list-fields` and
`item-fields` ignore the `allowed-fields` setting.

The `where-clause` setting allows you to set additional conditions that are
always set by default.

```YML
contenttypes:
    entries:
        item-fields: [ title, slug, teaser, image ]
        list-fields: [ title, slug, teaser, image ]
        allowed-fields: [ title, slug, teaser, image ]
        where-clause:
            status: 'published'
        order: title,-datepublished    
    pages:
        # use 'default' settings
```

### Images

For images, you'll get an absolute URL to that asset. By default, this extension
adds an absolute URL to a 400x300 thumbnail in the response. By overriding the
`thumbnail` setting, you can set a preferred `width` and `height`.

```YML
thumbnail:
    width: 400
    height: 300
```

### Headers

To work correctly with other tools that _read_ the JSON generated this extension,
it might be necessary to set the correct headers with the response. By default,
this extension sets the following:

```YML
headers:
    'Access-Control-Allow-Origin': '*'
    'Content-Type': 'application/vnd.api+json'
```

You can define additional headers as required, or tweak the existing ones.


Queries
-------

This extension implements the [JSON API](http://jsonapi.org/) specification,
as follows:

| URL         | Description                                                    |
|-------------|----------------------------------------------------------------|
|`/{ct}`      | Returns a list of records of the specified `contenttype`.      |
|`/{ct}/{id}` | Returns a single record of the specified `contenttype`.        |
|`/{ct}/{id}/{relatedtype}` | Returns a list of records that is related to the specified record with specified `id`. |

where `{ct}` means `{contenttype}`, the name of the specified contenttype.

### Query Parameters

The list call accepts some extra parameters (in the form of query string
parameters appended to the URL):

| Option       | Description                                                       |
|--------------|-------------------------------------------------------------------|
|`sort`        | Order the list by the specified field. Prefix the fieldname with a minus/hyphen to set the orderering to descending. Example `sort=id` or `sort=-id`. Note: Bolt currently does not support sorting on multiple fields. |
|`page[size]`  | Specify the number of items to return. Example: `page[size]=10` |
|`page[number]`| To be combined with `page[size]`: get the n-th page. This is 1-based, so `1` designates the first page. Example: `page[number]=1&page[size]=10`. |
|`include`     | Fetches all the related records of the specified contenttype(s) of the record(s) in the `included` field of the JSON response. Separate multiple contenttypes with a comma. Example: `include=pages`. |
|`fields[]`    | Set the fields that are shown in the response per specified contenttype. Separate multiple fields with commas. Multiple `fields[]` parameters are possible. Example: `fields[entries]=slug,teaser`. |
|`filter[]`    | Filter records where a certain field must be equal to the specified `{value}`. Multiple `filter[]` parameters are possible. Example: `filter[id]=1,2`. |
|`contains[]`  | Filter records where a certain field must contain the specified `{value}`. Multiple `contains[]` parameters are possible. Example: `contains[id]=1,2`. |


### Additional Queries

Besides the basic JSON API features, below are some additional Bolt specific
queries that you may find useful:

| URL                     | Description                                                 |
|-------------------------|-------------------------------------------------------------|
|`/{ct}/search?q={query}` | Searches for `{query}` in a specific `contenttype`.         |
|`/search?q={query}`      | Searches for `{query}` in all contenttypes.                 |
|`/menu`                  | Returns a list of all menus defined in `menu.yml`.          |
|`/menu?q={name}`         | Returns the menu with the specified name.                   |
|`/taxonomy`              | Returns a list of all taxonomies defined in `taxonomy.yml`. |
|`/taxonomy?q={name}`     | Returns the taxonomy with the specified name.               |

### Road Map

  * Better handling of menus.
  * Better handling of taxonomies.
  * More documentation.
  * Optimize queries.
  * Better search.
  * Split the `Extension.php` in multiple classes (i.e. `Helper`, `Response`).
  * Handle specific fieldtypes:
    * Handle JSON fields.
    * Handle select-contenttype fields.
  * Add hooks for handling specific fieldtypes.
  * Add i18n for `detail` field in error messages.
  * Add integration/hooks with other extensions, such as [`RelatedContentByTags`](https://github.com/xiaohutai/bolt-relatedcontentbytags).
