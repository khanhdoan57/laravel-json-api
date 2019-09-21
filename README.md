
# Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
    - [Global Middlewares](#global-middlewares)
    - [Authorization](#authorization)
    - [Resource config](#resource-config)
        - [Routes](#routes)
        - [Route configuration](#route-configuration)
        - [Resource middlewares](#resource-middlewares)
        - [Fillable fields](#fillable-fields)
        - [Validation](#validation)
        - [Sorting](#sorting)
        - [Filter](#filter)
        - [Relationships optimization](#relationships-optimization)
        - [Relationship pagination](#relationship-pagination)
- [Events](#events)
    - [Get a resource events](#get-a-resource-events)
    - [Get resource collection events](#get-resource-collection-events)
    - [Create resource events](#create-resource-events)
    - [Update resource events](#update-resource-events)
    - [Delete resource events](#delete-resource-events)
    - [Relationships events](#relationships-events)
    - [Relationship pagination](#relationship-pagination)
- [API Syntax](#api-syntax)
    - [Sorting results](#sorting-results)
    - [Filter and custom query](#filter-and-custom-query)
    - [Including and excluding fields](#including-and-excluding-fields)
- [Testing](#testing)

# Installation
- This is a private package, so you need to add this package to "repositories" of your composer.json file

```
# composer.json
{
    "require": {
        "hackerboy/laravel-json-api": "master",
        ...
    },
    ...

    "repositories": [
      {
        "type": "git",
        "url": "https://github.com/hackerboydotcom/laravel-json-api.git"
      }
    ]
}
```

- This package is based on hackerboy/json-api package, so please config json-api package first. More detail at: https://packagist.org/packages/hackerboy/json-api
- After sucessfully set up the `hackerboy/json-api` package. Now let's create a new file at "/config/laravel_jsonapi.php" like below


```
<?php
// /config/laravel_jsonapi.php

return [
    'jsonapi_config' => 'jsonapi', // Config name of hackerboy/json-api package (https://packagist.org/packages/hackerboy/json-api)
    'prefix' => '/api/', // API url prefix,
    'result_limit' => 20, // Default number of results / page for resource collection
    'maximum_result_limit' => 100, // Maximum number of results / page
];
```

- Now open your /config/app.php and register the package provider (you dont have to do this if you're using Laravel 5.5 or higher)

```
<?php
// /config/app.php

return [
    ...
    'providers' => [
        ...
        HackerBoy\LaravelJsonApi\LaravelJsonApiProvider::class
    ]
];
```

- Ok now we're ready to go, let's try to open http://localhost/api/[RESOURCE_TYPE]

# Configuration

All config for this package live in `/config/laravel_jsonapi.php`

## Global Middlewares

Set middlewares for all API methods

Config key | Type | Default | Required | Description
-----------|------|---------|----------|------------
middlewares | array | array() | false | Set middlewares for all API methods

Example:
```
<?php
// /config/laravel_jsonapi.php

return [
    ...other config...
    'middlewares' => ['middleware1', 'middleware2', ...]
];
```

## Authorization

This package support authorization with Laravel Policies, for more detail: https://laravel.com/docs/5.8/authorization#creating-policies.
To enable authorization feature, please check the config reference below:

Config key | Type | Default | Required | Description
-----------|------|---------|----------|------------
use_policies | bool | false | false | true: Enable authorization with policies, false: Disable authorization
deep_policy_check | bool | true | false | Deeply check policy permission for every single resource (checking both `viewAny` and `view` permission for collection of resource)
allow_guest_users | bool | false | false | Allow guest users to access API
user_resolver | callback | Laravel user resolver | false | User resolver to get current request user
guest_user_resolver | callback | new User() | false | Guest user resolver, default will be a new instance of User model

Policy permissions:

Policy action | Permission
--------------|-----------
viewAny | Can view collection of resource `GET /api/{RESOURCE_TYPE}`
view | Can view a single resource by ID `GET /api/{RESOURCE_TYPE}/{id}`
create | Can create new resources `POST /api/{RESOURCE_TYPE}`
update | Can update an existing resource `PATCH /api/{RESOURCE_TYPE}/{id}`
delete | Can delete an existing resource `DELETE /api/{RESOURCE_TYPE}/{id}`

All resources without "view" permission will be removed automatically from Document data. For example, request: `GET /api/users/1`, user 1 has many `posts` but all `posts` resources will not be included in the response if the request user don't have "viewAny" posts permission.

In case `deep_policy_check` is enabled (default), it will also check "view" permission for every single resource and remove unauthorized ones from response.

## Resource config

Configurations for every single resource are nested in "resources" key of `/config/laravel_jsonapi.php`, each resource configuration is an element with key name is model class name. For example:

```
<?php
// /config/laravel_jsonapi.php

return [
    ...other config...
    'resources' => [
        
        App\User::class => [
            ...configuration for "users" resources...
        ],

        App\Post::class => [
            ...configuration for "posts" resources...
        ],

        ...

    ],
];
```

### Routes

By default, any resources will support the following endpoint (in this example we have prefix is `/api/`):

Method | Endpoint | Route name | Description
-------|----------|------------|------------
GET | /api/{RESOURCE_TYPE}/{id} | `get` | View a single resource by id
GET | /api/{RESOURCE_TYPE} | `collection` | View collection of resource
POST | /api/{RESOURCE_TYPE} | `post` | Create new resource
PATCH | /api/{RESOURCE_TYPE}/{id} | `patch` | Update an existing resource
DELETE | /api/{RESOURCE_TYPE}/{id} | `delete` | Delete a resource

Besides, resources with relationships may support these following endpoints:

Method | Endpoint | Route name | Description
-------|----------|------------|------------
GET | /api/{RESOURCE_TYPE}/{id}/relationships/{RELATIONSHIP_RESOURCE_TYPE} | `getRelationships` | Get relationships of a resource with another resources. For example: /api/users/1/relationships/posts
GET | /api/{RESOURCE_TYPE}/{id}/{RELATIONSHIP_RESOURCE_TYPE} | `getRelationshipData` | Get collection of related resources. Example: /api/users/1/posts (Get all posts of user 1)
POST | /api/{RESOURCE_TYPE}/{id}/relationships/{RELATIONSHIP_RESOURCE_TYPE} | `postRelationships` | Create relationship between resources (https://jsonapi.org/format/#crud-updating-relationships)
PATCH | /api/{RESOURCE_TYPE}/{id}/relationships/{RELATIONSHIP_RESOURCE_TYPE} | `patchRelationships` | Create new and replace current relationships (https://jsonapi.org/format/#crud-updating-relationships)
DELETE | /api/{RESOURCE_TYPE}/{id}/relationships/{RELATIONSHIP_RESOURCE_TYPE} | `deleteRelationships` | Delete relationships (https://jsonapi.org/format/#crud-updating-relationships)

* Note: Not all relationships will support all 3 methods (POST, PATCH, DELETE), it depends on the relationship type. Check the reference table below:

Model relationship type | Supported Routes | Description
------------------------|------------------|--------------
[morphTo, morphOne](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations) | `patchRelationships` | Only support `PATCH` method
[belongsToMany](https://laravel.com/docs/5.8/eloquent-relationships#many-to-many), [morphToMany](https://laravel.com/docs/5.8/eloquent-relationships#many-to-many-polymorphic-relations) | `postRelationships`, `patchRelationships`, `deleteRelationships` | Support all methods
[morphMany](https://laravel.com/docs/5.8/eloquent-relationships#one-to-many-polymorphic-relations) | `postRelationships` | Only support `POST` method by default. You need to define custom relationship handler for other methods (Check Relationships optimization section below)
[hasOne, belongsTo](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one) | `patchRelationships` | Only support `PATCH` method
[hasMany](https://laravel.com/docs/5.8/eloquent-relationships#one-to-many) | `postRelationships` | Only support `POST` method by default. You need to define custom relationship handler for other methods (Check Relationships optimization section below)
[hasOneThrough](https://laravel.com/docs/5.8/eloquent-relationships#has-one-through), [hasManyThrough](https://laravel.com/docs/5.8/eloquent-relationships#has-many-through) | `none` | Not supported by default. You need to define custom relationship handlers (Check Relationships optimization section below).

*Write and delete methods (POST, PATCH, DELETE) for relationships will only work if [Relationships Optimization](#relationships-optimization) config were set.*

### Route configuration

Manually configuring resource routes is not required by default. But in case if you want to disable or just allow some specified routes, you will need to config it.

Route config are nested in [Resource Config](#resource-config) as an element with key is `routes`. For example:

```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'resources' => [
        ...
        App\User::class => [
            ...
            'routes' => [
                'get', 'collection', post, // Routes which are not listed will be disabled
                'delete' => function($id) { // You can define a custom action handler as a callback
                    return app()->make('laravel-json-api')->getController(App\User::class)->delete($id);
                }, 
                'getRelationships' => function(Illuminate\Http\Request $request, $id, $relationshipName) {}, // Type-hint and route params injection are supported as well
                'patch' => 'ControllerName@action' // A string of Controller@action with laravel syntax is also supported
            ]
        ]
    ]
];
```

### Resource middlewares

You can override [Global Middlewares](#global-middlewares) config for a specified resource by setting `middlewares` element inside [Resource Config](#resource-config). For example:

```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'resources' => [
        ...
        App\User::class => [
            ...
            'middlewares' => ['middleware1', 'middleware2']
        ]
    ]
];
```


### Fillable fields

Fillable config is about to let the package knows which fields are allowed for write request (POST, PATCH request). By default, your model `protected $fillable` data will be used for this config. But in case you want to override the data from model, you can set a `fillable` element inside [Resource Config](#resource-config). For example:

```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'resources' => [
        ...
        App\User::class => [
            ...
            'fillable' => ['name', 'email']
        ]
    ]
];
```

What if I want an attribute be fillable for "post" requests but not fillable for "patch" requests? It's easy, simply using validation rule `not_fillable` (this validation rule is implemented by this package), for more detail, see the example in [Validation](#validation) section

### Validation

You can set validation rules for write requests using [Laravel Validation](https://laravel.com/docs/5.8/validation#available-validation-rules). Validation config for a model are nested inside [Resource Config](#resource-config). You should set validation rules for both "post" and "patch" requests. For example

```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'resources' => [
        ...
        App\User::class => [
            ...
            'validation' => [
                'post' => [
                    'email' => 'required|email',
                    'name' => 'required|min:6',
                    'password' => 'required|min:6'
                ],
                'patch' => [
                    'email' => 'not_fillable', // Email is not allowed by updating
                    'name' => 'min:6',
                    'password' => 'min:6'
                ]
            ]
        ]
    ]
];
```

### Sorting

You can set which fields are allowed for sorting by using `sortable` config element nested inside [Resource Config](#resource-config), you can also define your custom handler. For example:

```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'resources' => [
        ...
        App\User::class => [
            ...
            'sortable' => [
                'id', 'created_at', // Allowed fields for sorting
                'updated_at' => function($query, $sortType) {

                    // Custom sorting handler
                    $query->orderBy('updated_at', $sortType);
                } 
            ],

            'max_multiple_sorting' => 2, // Optional, determine maximum number of fields can be used for sorting at once. Default: 2
        ]
    ]
];
```

### Filter

You can set which fields are allowed for filtering and custom query (check [API Syntax](#api-syntax)) by using `filter` config element nested inside [Resource Config](#resource-config).

By default, fields listed in `filter` will support the following query operator: `=, !=, >, <, >=, <=`. You can also define your custom query handler. For example:


```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'resources' => [
        ...
        App\User::class => [
            ...
            'max_query_conditions' => 5, // Optional, determine maximum number of query conditions. Default 5
            'filter' => [
                'id', 'email', // Allowed fields for filter and custom query
                'name' => function($queryData, $query) { // Or you can define your own filter handler

                    /** Example of queryData
                    $queryData = [
                        'field' => '...', // Field name
                        'value' => '...', // Query value
                        'type' => '...', // Could be basic / null / notnull
                        'operator' => '...', // Could be =, !=, >, <, >=, <=
                        'boolean' => '...', // Could be, null, "and", "or"
                    ];
                    */

                    // Custom query handler
                    $whereMethod = (isset($queryData['boolean']) and $queryData === 'or') ? 'orWhere' : 'where';
                    $query->{$whereMethod}('name', 'like', $queryData['value']);
                } 
            ]
        ]
    ]
];
```

### Relationships optimization

Relationships optimization is not required but highly recommended. You should define all relationships for query optimization and relationships write/delete methods.

Before declaring relationships optimization, please make sure that you already defined relationships in your [hackerboy/json-api](https://packagist.org/packages/hackerboy/json-api) Resource Schema classes.

Resource relationships config are nested inside [Resource config](#resource-config) with key name is `relationships`. Each relationship resource is an element inside relationships config with key name is relationship name. For example:

Inside model class, suppose that we have a Post model like this:
```
<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Post extends Model {

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function comments()
    {
        return $this->morphMany('App\Comment');
    }

}
```

And we have a [JSON:API Resource Schema](https://github.com/hackerboydotcom/json-api/#create-your-resource-schema) for Post model like this:
```
<?php

namespace App\Http\JsonApiResources;

use HackerBoy\JsonApi\Abstracts\Resource;

class PostResource extends Resource {

    protected $type = 'posts';

    public function getId()
    {
        return $this->model->id;
    }

    public function getAttributes()
    {
        return [
            'title' => $this->model->title,
            'content' => $this->model->content
        ];
    }

    public function getRelationships()
    {
        return [
            'author' => $this->model->user,
            'comments' => $this->model->comments
        ];
    }
}

```

Now let's open `/config/laravel_jsonapi.php` to declare relationships optimization
```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'resources' => [
        ...
        App\Post::class => [
            ...
            'relationships' => [

                // Declare "author" relationships
                'author' => [
                    'property' => 'user', // Property name of Post model to get relationship resources.
                    'write' => true, // Optional config, true: Allow "write/delete" requests for this relationship, false: disable write/delete requests for this relationship. Default: true
                ],

                // Declare "comments" relationships
                'comments' => [
                    'property' => 'comments',
                    'included' => ['get', 'collection', 'getRelationships', ...], // Optional config, define which routes will include "comments" resources
                    'write' => function($request, $modelObject, $relationshipData) {}, // You can also define a custom handler for relationship write requests.
                    'use_observers' => true, // Using observers while saving relationship objects. Default: true (If you are using auto write handler)
                ]
            ]
        ]
    ]
];
```

### Relationship pagination

In case of "to many" relationships, you may need to paginate the relationship data. For example, a post resource may has hundreds of comments, so we can't show them all in one page. 

Fortunately, we can make it paginated easily using `RelationHandler`. To implement pagination for relationship data, we just need to wrap `Laravel's Relation` in `RelationHandler::handle()` method and call to `paginate()` method.

Example:

```
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use HackerBoy\LaravelJsonApi\Handlers\RelationHandler;

class Post extends Model
{
    public function comments()
    {
        return RelationHandler::handle($this->morphMany(Comment::class, 'resource'))->paginate();
        // If you want to specify the limit number of relationship data per page, simply put an integer as the first param like ->paginate(10)
    }
}

```

The post `comments` relationship is now paginated. But we also want to show pagination links in the response document, so we need to add 'links' element to the `comments` relationship data in `PostResource`.

Example:

```
<?php

namespace App\Http\JsonApiResources;

use HackerBoy\JsonApi\Abstracts\Resource;

class PostResource extends Resource {

    ...

    public function getRelationships()
    {
        return [
            'comments' => [
                'data' => $this->model->comments,
                'links' => $this->model->comments()->getJsonApiLinks()
            ]
        ];
    }
}

```

It's all set now. The default limit value for relationship data is 20 and maximum limit is 100. If you want to change the default value, add these two members to your `/config/laravel_jsonapi.php`:

```
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'relationship_result_limit' => 20, // Default is 20
    'relationship_maximum_result_limit' => 100, // Default is 100
];
```

API consumers now can fetch the post relationships with `page` and `limit` query params. For example:

```
GET https://example.com/api/posts/1/relationships/comments?page=1&limit=30
```

#### Relationship pagination total data count

You can set total data count for the relationship pagination by using method `setCount(int $count)`

For example:

```
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use HackerBoy\LaravelJsonApi\Handlers\RelationHandler;

class Post extends Model
{
    public function comments()
    {
        return RelationHandler::handle($this->morphMany(Comment::class, 'resource'))
                    ->paginate()
                    ->setCount($this->comment_count);
    }
}

```

# Events

Events are registered under "events" member in your config

```php
<?php
// /config/laravel_jsonapi.php

return [
    ...
    'events' => [
        '{EVENT-NAME}' => function(){...},
        '{EVENT-NAME}' => ['Class', 'method'],
        ...
    ]
];
```

Below is the list of all event names:

## Get a resource events

These events will be triggered in GET request for a single resource.

### get.query

This event will be triggered before the get query is executed.

Param | type | Description
------|------|------------
$modelClass | string | Model class name
$query | object | Laravel query object

### get.beforeReturn

Param | type | Description
------|------|------------
$modelObject | object | Laravel Eloquent model object
$document | object | HackerBoy\JsonApi\Document object

## Get resource collection events

These events will be triggered in GET request for resource collection.

### collection.query

This event will be triggered before the get query is executed.

Param | type | Description
------|------|------------
$modelClass | string | Model class name
$query | object | Laravel query object

### collection.afterQuery

This event will be triggered after the get query is executed.

Param | type | Description
------|------|------------
$collection | object | Laravel Collection of model objects

### collection.beforeReturn

Param | type | Description
------|------|------------
$collection | object | Laravel Collection of model objects
$document | object | HackerBoy\JsonApi\Document object

## Create resource events

### post.saving

This event will be triggered before the save query is executed.

Param | type | Description
------|------|------------
$model | object | Laravel model object

### post.saved

This event will be triggered after the save query is executed.

Param | type | Description
------|------|------------
$model | object | Laravel model object

## Update resource events

### patch.query

This event will be triggered before the finding query is executed.

Param | type | Description
------|------|------------
$modelClass | string | Model class name
$query | object | Laravel database query object

### patch.saving

This event will be triggered before the save query is executed.

Param | type | Description
------|------|------------
$model | object | Laravel model object

### patch.saved

This event will be triggered after the save query is executed.

Param | type | Description
------|------|------------
$model | object | Laravel model object

## Delete resource events

### delete.query

This event will be triggered before the finding query is executed.

Param | type | Description
------|------|------------
$modelClass | string | Model class name
$query | object | Laravel database query object

### delete.deleting

This event will be triggered before the delete query is executed.

Param | type | Description
------|------|------------
$model | object | Laravel model object

### delete.deleted

This event will be triggered after the delete query is executed.

Param | type | Description
------|------|------------
$model | object | Laravel model object

### delete.beforeReturn

This event will be triggered before returning.

Param | type | Description
------|------|------------
$model | object | Laravel model object
$document | object | HackerBoy\JsonApi\Document object

## Relationships events

### relationships.beforeReturn

This event will be triggered in get relationships request (For example: /api/posts/1/comments).

Param | type | Description
------|------|------------
$model | object | Laravel model object
$relationshipName | string | Relationship name
$document | object | HackerBoy\JsonApi\Document object

### relationships.saving

This event will be triggered before the relationships be saved.

Param | type | Description
------|------|------------
$model | object | Laravel model object
$relationshipName | string | Relationship name
$relationshipData | array | The relationship data


### relationships.saving

This event will be triggered after the relationships be saved.

Param | type | Description
------|------|------------
$model | object | Laravel model object
$relationshipName | string | Relationship name
$relationshipData | array | The relationship data

# API Syntax
## Sorting results

After set [Resource Sortable](#sorting) config. You can sort the results with [JSON:API standard](https://jsonapi.org/format/#fetching-sorting). For example

`GET https://localhost/api/users?sort=created_at,updated_at`

For descrease sorting, simply using "minus" as prefix

`GET https://localhost/api/users?sort=-created_at,-updated_at`

## Filter and custom query

After set [Resource Filter](#filter) config. You can now filter the results with [JSON:API standard](https://jsonapi.org/recommendations/#filtering). For example:

`GET https://localhost/api/users?filter[email]=example@gmail.com&filter[name]=Example`

Alternatively, for complex queries, you can make a custom query using `_query` param.

`_query` param must be a JSON string. Can be a [query object](#query-objects), [query group object](#query-group-objects) or an array of multiple query / query group objects. For example:

`GET https://localhost/api/users?_query=[{"field":"email","value":"example@gmail.com"},{"field":"name","value":"test","boolean":"or"}]`

### Query objects

Query object is a JSON object for querying results from API. Below is the introduction of query object structure:

```
{
    "field": "...", // Required, field name for query
    "type": "...", // Optional, possible values: basic, null (for $query->whereNull()), notNull (for $query->whereNotNull()). Default is "basic"
    "value": "...", // Required if type is "basic"
    "operator": "...", // Optional, possible values: =, !=, >, <, >=, <=. Default is "="
    "boolean": "...", // Optional, determine "where operator", possible values: "and", "or". Default: "and"
}
```

### Query group objects

Query group is also supported for custom query. Below is the instroduction of query group object structure:

```
{
    "boolean": "...", // Optional. Same as query objects. Default value: "and"
    "query": [ // Required. An array of query object or query group object.
        queryObject,
        queryObject,
        {
            "boolean": "or",
            "query": {
                queryObject,
                queryObject,
                ...
            }
        }
        ...
    ]
}
```

### Example queries

Simple query examples:

```
{
    "field": "email",
    "value": "example@gmail.com"
}
// Equal to: WHERE `email` = 'example@gmail.com'
```
```
{
    "field": "post_count",
    "value": 10,
    "operator": ">"
}
// Equal to: WHERE `post_count` > 10
```

Multiple query example:
```
[
    {
        "field": "email",
        "value": "example@gmail.com"
    },
    {
        "field": "post_count",
        "value": 10,
        "operator": ">"
    }
]
// Equal to: WHERE `email` = 'example@gmail.com' AND `post_count` > 10
```
```
[
    {
        "field": "email",
        "value": "example@gmail.com"
    },
    {
        "field": "post_count",
        "value": 10,
        "operator": ">",
        "boolean": "or"
    }
]
// Equal to: WHERE `email` = 'example@gmail.com' OR `post_count` > 10
```

Multiple-nested query example:
```
[
    {
        "field": "email",
        "value": "example@gmail.com"
    },
    {
        "boolean": "or",
        "query": [
            {
                "field": "name",
                "value": "example"
            },
            {
                "field": "post_count",
                "value": 10,
                "operator": ">"
            }
        ]
    }
]
// Equal to: WHERE `email` = 'example@gmail.com' OR (`name` = 'example' AND `post_count` > 10)
```

## Including and excluding fields

You can define which fields should be included / excluded from resposne document using `includes` and `excludes` params.

Syntax:
```
?includes[resource-type]=field1,field2
?excludes[resource-type]=field1,field2
```

Example:

`GET /api/users?includes[users]=name,email&excludes[posts]=content,description`

*Return only "email", "name" attributes for `users` resources and excludes "content", "description" attributes from `posts` resources*

# Testing

We use another repository (a Laravel project) for testing as this package requires Laravel environment to work. For more detail, please check: https://github.com/hackerboydotcom/laravel-json-api-test