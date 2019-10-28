<?php

namespace HackerBoy\LaravelJsonApi;

use HackerBoy\JsonApi\Document;

class LaravelJsonApi {

    protected $document;

    protected $config = [];

    protected $router;

    protected $controllers = [];

    /**
    * @param object Document object
    * @param config array
    *
    * Example of config:
    * $config = [
    *    'jsonapi_config' => 'jsonapi', // If your config is /config/jsonapi.php
    *    'result_limit' => 20, // Default 20
    *    'maximum_result_limit' => 100, // Default 100
    *    'relationship_result_limit' => 20, // Default 20
    *    'relationship_maximum_result_limit' => 100, // Default 100
    *    'prefix' => 'api',
    *    'use_policies' => true, // Check policy
    *    'deep_policy_check' => false, // Check policy "view" permission on every single resource in collection
    *    'user_resolver' => function() {}, // Resolve user - default will use \Auth::user()
    *    'allow_guest_users' => true, // Default false
    *    'guest_user_resolver' => function() {}, // Default will be new App\User;
    *    'events' => [
    *        'get.query' => function($modelClass, $query) {},
    *        'get.beforeReturn' => function($modelObject, $document) {},
    *
    *        'relationships.beforeReturn' => function($modelObject, $relationshipName, $document) {},
    *
    *        'collection.query' => function($modelClass, $query) {},
    *        'collection.afterQuery' => function($collection) {},
    *        'collection.beforeReturn' => function($collection, $document) {},
    *
    *        'post.saving' => function($modelObject) {},
    *        'post.saved' => function($modelObject) {},
    *
    *        'patch.query' => function($modelClass, $query) {},
    *        'patch.saving' => function($) {},
    *        'patch.saved' => function($modelClass, $query) {},
    *
    *        'relationships.saving' => function($modelObject, $relationshipName, $relationshipData) {},
    *        'relationships.saved' => function($modelObject, $relationshipName, $relationshipData) {},
    *
    *        'delete.query' => function($modelClass, $query) {},
    *        'delete.deleting' => function($modelObject) {},
    *        'delete.deleted' => fucntion($modelObject) {},
    *        'delete.beforeReturn' => function($modelObject, $document) {}
    *    ],
    *    'resources' => [
    *        Model::class => [
    *            
    *            // Define available routes or specify the handler - default will have all routes
    *            'routes' => [
    *                'get', 'collection', 
    *                'post' => function() { },
    *                'patch',
    *                'delete',
    *                'getRelationships', 'postRelationships', 'patchRelationships', 'deleteRelationships', 
    *                'getRelationshipData', 
    *            ],
    *            
    *            // Fillable - fields that can be written - default will load from model fillable variable
    *            'fillable' => [...],
    *            
    *            // Sortable fields
    *            'sortable' => [
    *                'created_at', 'updated_at',
    *                'custom_field' => function($query) {
    *                    // Custom handler
    *                }
    *            ],
    *
    *            // Maximum number of fields for multiple field sorting
    *            'max_multiple_sorting' => 2, // Default 2
    *
    *            // Filter - fields that can be query
    *            'filter' => ['field1', 'field2', 'field3', 
    *                'field4' => function($queryData, $queryObject) {
    *                                // Custom filter
    *                                $query->where('field4', $queryData['value']);
    *                            }
    *            ],
    *            
    *            'max_query_conditions' => 5, // Max number of query condition - default 5
    *
    *            // Validation
    *            'validation' => [
    *                'post' => [
    *                    'field' => 'required|min:6',
    *                    ...
    *                ],
    *                'patch' => []
    *            ],
    *            
    *            // Override global middleware config
    *            'middleware' => [
    *                'get' => ['...'],
    *                'post' => ['...'],
    *                ...
    *            ],
    *
    *            // Relationship optimization
    *            'relationships' => [
    *                'relationship_name' => [
    *                    'relation' => 'relation method name', // Recommended - for query optimization
    *                    'included' => ['get', 'collection'] // Define included in which method, default will be all,
    *                    'write' => function($resourceModel, $relationshipData) {}, // True for auto handle Or false to turn it off
    *                    'use_observers' => true, // Default is true. Use observers for relationships write (when using auto handler)
    *                ]
    *            ]
    *        
    *        ]
    *    ],
    *    'middleware' => ['...'] // Global middleware
    * ];
    */
    public function __construct($config = [])
    {
        if (!isset($config['jsonapi_config'])) {
            return;
        }

        $this->document = new Document(config($config['jsonapi_config']));
        $this->config = $config;

        // Load config to jsonapi helper
        Helpers\JsonApi::load($this->document, config($config['jsonapi_config']));

        // Remove last slash
        $this->config['prefix'] = @rtrim($this->config['prefix'], '/');

        // Set url
        $this->document->setUrl($this->config['prefix']);

        // Init controller
        $this->router = new Http\Router($this->document, $this->config);
    }

    /**
    * Get document
    *
    * @param void
    * @return \HackerBoy\JsonApi\Document
    */
    public function getDocument()
    {
        return $this->document;
    }

    /**
    * Get config
    */
    public function getConfig()
    {
        return $this->config;
    }

    /**
    * Get router object
    */
    public function getRouter()
    {
        return $this->router;
    }

    /**
    * Get controller object
    */
    public function getController($modelClass)
    {
        return isset($this->controllers[$modelClass]) ? $this->controllers[$modelClass] : ($this->controllers[$modelClass] = new Http\Controller($this->document, $modelClass, $this->config));
    }
}