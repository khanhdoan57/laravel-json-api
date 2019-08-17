
# Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
    - [Middlewares](#middlewares)
    - [Authorization](#authorization)
    - [Resource config](#resource-config)
        - [Routes](#routes)
        - [Fillable fields](#fillable-fields)
        - [Sorting](#sorting)
        - [Filter](#filter)
        - [Validation](#validation)
        - [Resource middlewares](#resource-middlewares)
        - [Relationships optimization](#relationships-optimization)
- [Events](#events)
    - [Get a resource events](#get-a-resource-events)
    - [Get resource collection events](#get-resource-collection-events)
    - [Create resource events](#create-resource-events)
    - [Update resource events](#update-resource-events)
    - [Delete resource events](#delete-resource-events)
    - [Relationships events](#relationships-events)
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

    "repositories": {
      "laravel-json-api": {
        "type": "git",
        "url": "https://github.com/hackerboydotcom/laravel-json-api.git"
      }
    }
}
```

- This package is based on hackerboy/json-api package, so please config json-api package first. More detail at: https://packagist.org/packages/hackerboy/json-api
- After sucessfully installed the package. Now let's create a new file at "/config/laravel_jsonapi.php" like below


```
<?php
// /config/laravel_jsonapi.php

return [
    'jsonapi_config' => '...', // Config name of hackerboy/json-api package (https://packagist.org/packages/hackerboy/json-api)
    'prefix' => '/api/', // API url prefix,
    'result_limit' => 20, // Default number of results / page for resource collection
    'maximum_result_limit' => 100, // Maximum number of results / page
];
```

- Now open your /config/app.php and register the package provider.

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

## Middlewares
## Authorization
## Resource config
### Routes
### Fillable fields
### Sorting
### Filter
### Validation
### Resource middlewares
### Relationships optimization

# Events

# Api Syntax

# API Syntax

# Testing

We use another repository (a Laravel project) for testing as this package requires Laravel environment to work. For more detail, please check: https://github.com/hackerboydotcom/laravel-json-api-test