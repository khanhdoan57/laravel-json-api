<?php

namespace HackerBoy\LaravelJsonApi\Http;

use HackerBoy\JsonApi\Document;
use Illuminate\Support\Facades\Route;

class Router {

    protected $document;

    protected $config;

    /**
    * @param object Document object
    * @param array Config
    */
    public function __construct(Document $document, $config = [])
    {
        $this->document = $document;
        $this->config = $config;
    }

    /**
    * Generate routes
    */
    public function generate()
    {
        $documentConfig = $this->document->getConfig();
        $resourceMap = $documentConfig['resource_map'];

        if (class_exists('Laravel\Lumen\Application')) {
            return $this->lumenGenerate($documentConfig, $resourceMap);
        }

        return $this->laravelGenerate($documentConfig, $resourceMap);

    }

    /**
    * Laravel generate
    */
    private function laravelGenerate($documentConfig, $resourceMap)
    {
        foreach ($documentConfig['resource_map'] as $modelClass => $resourceClass) {
            
            $model = new $modelClass;
            $resource = new $resourceClass($model, $this->document);
            $resourceType = $resource->getType($model);

            // Route group
            Route::prefix((isset($this->config['prefix']) ? $this->config['prefix'] : '/').'/'.$resourceType)->name($resourceType.'.')->group(function() use ($modelClass) {

                $defaultRouteData = ['get', 'collection', 'post', 'patch', 'delete', 'getRelationships', 'postRelationships', 'patchRelationships', 'deleteRelationships', 'getRelationshipData'];
                $routeData = isset($this->config['resources'][$modelClass]['routes']) ? $this->config['resources'][$modelClass]['routes'] : $defaultRouteData;

                $routes = [];
                $actions = [];

                foreach ($routeData as $key => $value) {
                    
                    // Route has custom controller action
                    if (in_array($key, $defaultRouteData, true)) {
                        $routes[] = $key;
                        $actions[$key] = $value;
                        continue;
                    }

                    // Normal route
                    if (in_array($value, $defaultRouteData)) {
                        $routes[] = $value;
                        continue;
                    }

                }
            
                foreach ($routes as $method) {

                    // Get middleware
                    $middleware = $this->getRouteMiddleware($modelClass, $method);

                    // Get relationship
                    if ($method === 'getRelationships') {

                        Route::get('{id}/relationships/{relationshipName}', isset($actions[$method]) ? $actions[$method] : function($id, $relationshipName) use ($modelClass) {

                            // Auto controller
                            $controller = app()->make('laravel-json-api')->getController($modelClass);
                            return $controller->relationships($id, $relationshipName);

                        })->name($method)->middleware($middleware);

                        continue;
                    }

                    // Get relationship data
                    if ($method === 'getRelationshipData') {

                        Route::get('{id}/{relationshipName}', isset($actions[$method]) ? $actions[$method] : function($id, $relationshipName) use ($modelClass) {

                            // Auto controller
                            $controller = app()->make('laravel-json-api')->getController($modelClass);
                            return $controller->relationships($id, $relationshipName, 'resource');

                        })->name($method)->middleware($middleware);

                        continue;
                    }

                    // Write/delete relationships
                    if (in_array($method, ['postRelationships', 'patchRelationships', 'deleteRelationships'])) {

                        $method = str_replace('Relationships', '', $method);

                        Route::{$method}('{id}/relationships/{relationshipName}', isset($actions[$method]) ? $actions[$method] : function($id, $relationshipName) use ($modelClass) {

                            // Auto controller
                            $controller = app()->make('laravel-json-api')->getController($modelClass);
                            return $controller->storeRelationships($id, $relationshipName);

                        })->name($method)->middleware($middleware);

                        continue;
                    }

                    // Define route
                    Route::{$method === 'collection' ? 'get' : $method}(
                        in_array($method, ['get', 'patch', 'delete']) ? '{id}' : '/', 
                        isset($actions[$method]) ? $actions[$method] :
                            function($id = null) use ($method, $modelClass) {

                                // Auto controller
                                $controller = app()->make('laravel-json-api')->getController($modelClass);

                                return $controller->{$method}($id);

                            }
                    )->name($method)->middleware($middleware);

                }

            });

            // Destroy objects
            unset($resource);
            unset($model);

        }
    }

    /**
    * Lumen generate
    */
    private function lumenGenerate($documentConfig, $resourceMap)
    {
        $router = app()->router;

        foreach ($documentConfig['resource_map'] as $modelClass => $resourceClass) {
            
            $model = new $modelClass;
            $resource = new $resourceClass($model, $this->document);
            $resourceType = $resource->getType($model);

            // Route group
            $router->group([
                'prefix' => (isset($this->config['prefix']) ? $this->config['prefix'] : '/').'/'.$resourceType,
                'as' => $resourceType
            ], function() use ($modelClass, $router) {

                $defaultRouteData = ['get', 'collection', 'post', 'patch', 'delete', 'getRelationships', 'postRelationships', 'patchRelationships', 'deleteRelationships', 'getRelationshipData'];
                $routeData = isset($this->config['resources'][$modelClass]['routes']) ? $this->config['resources'][$modelClass]['routes'] : $defaultRouteData;

                $routes = [];
                $actions = [];

                foreach ($routeData as $key => $value) {
                    
                    // Route has custom controller action
                    if (in_array($key, $defaultRouteData, true)) {
                        $routes[] = $key;
                        $actions[$key] = $value;
                        continue;
                    }

                    // Normal route
                    if (in_array($value, $defaultRouteData)) {
                        $routes[] = $value;
                        continue;
                    }

                }
            
                foreach ($routes as $method) {

                    // Get middleware
                    $middleware = $this->getRouteMiddleware($modelClass, $method);

                    // Get relationship
                    if ($method === 'getRelationships') {

                        // Route data
                        $routeData = [
                            'as' => $method,
                            'middleware' => $middleware,
                        ];

                        // Check callback
                        if (isset($actions[$method]) and is_string($actions[$method])) {
                            $routeData['uses'] = $actions[$method];
                        } else {

                            // Handler
                            $routeData[] = function($id, $relationshipName) use ($actions, $method, $modelClass) {

                                if (isset($actions[$method]) and is_callable($actions[$method])) {
                                    return call_user_func_array($actions[$method], [$id, $relationshipName]);
                                }

                                // Auto controller
                                $controller = app()->make('laravel-json-api')->getController($modelClass);
                                return $controller->relationships($id, $relationshipName);

                            };
                        }

                        $router->get('{id}/relationships/{relationshipName}', $routeData);
                        continue;
                    }

                    // Get relationship data
                    if ($method === 'getRelationshipData') {

                        // Route data
                        $routeData = [
                            'as' => $method,
                            'middleware' => $middleware,
                        ];

                        // Check callback
                        if (isset($actions[$method]) and is_string($actions[$method])) {
                            $routeData['uses'] = $actions[$method];
                        } else {

                            // Handler
                            $routeData[] = function($id, $relationshipName) use ($actions, $method, $modelClass) {

                                if (isset($actions[$method]) and is_callable($actions[$method])) {
                                    return call_user_func_array($actions[$method], [$id, $relationshipName]);
                                }

                                // Auto controller
                                $controller = app()->make('laravel-json-api')->getController($modelClass);
                                return $controller->relationships($id, $relationshipName, 'resource');

                            };
                        }

                        $router->get('{id}/{relationshipName}', $routeData);
                        continue;
                    }

                    // Write/delete relationships
                    if (in_array($method, ['postRelationships', 'patchRelationships', 'deleteRelationships'])) {

                        // Route data
                        $routeData = [
                            'as' => $method,
                            'middleware' => $middleware,
                        ];

                        // Check callback
                        if (isset($actions[$method]) and is_string($actions[$method])) {
                            $routeData['uses'] = $actions[$method];
                        } else {

                            // Handler
                            $routeData[] = function($id, $relationshipName) use ($actions, $method, $modelClass) {

                                if (isset($actions[$method]) and is_callable($actions[$method])) {
                                    return call_user_func_array($actions[$method], [$id, $relationshipName]);
                                }

                                // Auto controller
                                $controller = app()->make('laravel-json-api')->getController($modelClass);
                                return $controller->storeRelationships($id, $relationshipName);

                            };
                        }

                        $router->{str_replace('Relationships', '', $method)}('{id}/relationships/{relationshipName}', $routeData);
                        continue;
                    }

                    // Define route
                    $routeData = [
                        'as' => $method,
                        'middleware' => $middleware,
                    ];

                    // Check callback
                    if (isset($actions[$method]) and is_string($actions[$method])) {
                        $routeData['uses'] = $actions[$method];
                    } else {

                        // Handler
                        $routeData[] = function($id = null) use ($actions, $method, $modelClass) {

                            if (isset($actions[$method]) and is_callable($actions[$method])) {
                                return call_user_func_array($actions[$method], [$id]);
                            }

                            // Auto controller
                            $controller = app()->make('laravel-json-api')->getController($modelClass);
                            return $controller->{$method}($id);

                        };
                    }

                    $router->{$method === 'collection' ? 'get' : $method}(
                        in_array($method, ['get', 'patch', 'delete']) ? '{id}' : '/', 
                        $routeData
                    );

                }

            });

            // Destroy objects
            unset($resource);
            unset($model);

        }
    }

    /**
    * Get route middleware
    */
    private function getRouteMiddleware($modelClass, $routeName)
    {
        if (isset($this->config['resources'][$modelClass]['middlewares'][$routeName])) {
            return $this->config['resources'][$modelClass]['middlewares'][$routeName];
        }

        if (isset($this->config['resources'][$modelClass]['middleware'][$routeName])) {
            return $this->config['resources'][$modelClass]['middleware'][$routeName];
        }

        if (isset($this->config['middlewares'])) {
            return $this->config['middlewares'];
        }

        if (isset($this->config['middleware'])) {
            return $this->config['middleware'];
        }

        return [];
    }
}