
# Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
- [API Syntax](#api-syntax)

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

/config/laravel_jsonapi.php

```
<?php

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

# API Syntax