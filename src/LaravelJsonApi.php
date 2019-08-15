<?php

namespace HackerBoy\LaravelJsonApi;

use HackerBoy\JsonApi\Document;

class LaravelJsonApi {

	protected $document;

	protected $config = [];

	protected $router;

	/**
	* @param object Document object
	* @param config array
	*
	* Example of config:
	* $config = [
	*	'jsonapi_config' => 'jsonapi', // If your config is /config/jsonapi.php
	*	'result_limit' => 20,
	*	'maximum_result_limit' => 'api',
	*	'prefix' => 'api',
	*	'use_policies' => true, // Check policy
	*	'user_resolver' => function() {}, // Resolve user - default will use \Auth::user()
	*	'allow_guest_users' => true, // Default false
	*	'guest_user_resolver' => function() {}, // Default will be new App\User;
	*	'events' => [
	*		'get.query' => function($modelClass, $query) {},
	*		'get.beforeReturn' => function($modelObject, $document) {},
	*
	*		'relationships.beforeReturn' => function($modelObject, $relationshipName, $document) {},
	*
	*		'collection.query' => function($modelClass, $query) {},
	*		'collection.afterQuery' => function($collection) {},
	*		'collection.beforeReturn' => function($collection, $document) {},
	*
	*		'post.saving' => function($modelObject) {},
	*		'post.saved' => function($modelObject) {},
	*
	*		'patch.query' => function($modelClass, $query) {},
	*		'patch.saving' => function($) {},
	*		'patch.saved' => function($modelClass, $query) {},
	*
	*		'relationships.saving' => function($modelObject, $relationshipName, $relationshipData) {},
	*		'relationships.saved' => function($modelObject, $relationshipName, $relationshipData) {},
	*
	*		'delete.query' => function($modelClass, $query) {},
	*		'delete.deleting' => function($modelObject) {},
	*		'delete.deleted' => fucntion($modelObject) {},
	*		'delete.beforeReturn' => function($modelObject, $document) {}
	*	],
	*	'resources' => [
	*		Model::class => [
	*			
	*			// Define available routes or specify the handler - default will have all routes
	*			'routes' => [
	*				'get', 'collection', 
	*				'post' => function() { },
	*				'patch',
	*				'delete',
	*				'getRelationships', 'postRelationships', 'patchRelationships', 'deleteRelationships', 
	*				'getRelationshipData', 
	*			],
	*			
	*			// Fillable - default will load from model fillable variable
	*			'fillable' => [...],		
	*
	*			// Validation
	*			'validation' => [
	*				'post' => [
	*					'field' => 'required|min:6',
	*					...
	*				],
	*				'patch' => []
	*			],
	*			
	*			// Override global middleware config
	*			'middlewares' => [
	*				'get' => ['...'],
	*				'post' => ['...'],
	*				...
	*			],
	*
	*			// Relationship optimization
	*			'relationships' => [
	*				'relationship_name' => [
	*					'property' => 'property name', // Recommended - for query optimization
	*					'included' => ['get', 'collection'] // Define included in which method, default will be all,
	*					'write' => function($resourceModel, $relationshipData) {} // Or false to turn it off
	*				]
	*			]
	*		
	*		]
	*	],
	*	'middlewares' => ['...'] // Global middleware
	* ];
	*/
	public function __construct($config = [])
	{
		if (!isset($config['jsonapi_config'])) {
			throw new \Exception('jsonapi_config is required');
		}

		$this->document = new Document(config($config['jsonapi_config']));
		$this->config = $config;

		// Load config to jsonapi helper
		Helpers\JsonApi::load($this->document, config($config['jsonapi_config']));

		// Load config to authorization helper
		Helpers\Authorization::setConfig($this->config);

		// Remove last slash
		$this->config['prefix'] = @rtrim($this->config['prefix'], '/');

		// Set url
		$this->document->setUrl($this->config['prefix']);

		$this->router = new Http\Router($this->document, $this->config);
	}

	/**
	* Get router object
	*/
	public function getRouter()
	{
		return $this->router;
	}
}