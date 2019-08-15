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

		foreach ($documentConfig['resource_map'] as $modelClass => $resourceClass) {
			
			$model = new $modelClass;
			$resource = new $resourceClass($model, $this->document);
			$resourceType = $resource->getType($model);

			// Route group
			Route::prefix((isset($this->config['prefix']) ? $this->config['prefix'] : '/').'/'.$resourceType)->group(function() use ($modelClass) {

				$defaultRouteData = ['get', 'collection', 'post', 'patch', 'delete', 'getRelationships', 'postRelationships', 'patchRelationships', 'deleteRelationships', 'getRelationshipData'];
				$routeData = isset($this->config['resources'][$modelClass]['routes']) ? $this->config['resources'][$modelClass]['routes'] : $defaultRouteData;

				$routes = [];
				$actions = [];

				foreach ($routeData as $key => $value) {
					
					// Route has custom controller actionn
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

					// Get relationship
					if ($method === 'getRelationships') {

						Route::get('{id}/relationships/{relationshipName}', isset($actions[$method]) ? $actions[$method] : function($id, $relationshipName) use ($modelClass) {

							// Auto controller
							$controller = new Controller($this->document, $modelClass, $this->config);
							return $controller->relationships($id, $relationshipName);

						});

						continue;
					}

					// Get relationship data
					if ($method === 'getRelationshipData') {

						Route::get('{id}/{relationshipName}', isset($actions[$method]) ? $actions[$method] : function($id, $relationshipName) use ($modelClass) {

							// Auto controller
							$controller = new Controller($this->document, $modelClass, $this->config);
							return $controller->relationships($id, $relationshipName, 'resource');

						});

						continue;
					}

					// Write/delete relationships
					if (in_array($method, ['postRelationships', 'patchRelationships', 'deleteRelationships'])) {

						$method = str_replace('Relationships', '', $method);

						Route::{$method}('{id}/relationships/{relationshipName}', isset($actions[$method]) ? $actions[$method] : function($id, $relationshipName) use ($modelClass) {

							// Auto controller
							$controller = new Controller($this->document, $modelClass, $this->config);
							return $controller->storeRelationships($id, $relationshipName);

						});

					}

					// Define route
					Route::{$method === 'collection' ? 'get' : $method}(
						in_array($method, ['get', 'patch', 'delete']) ? '{id}' : '/', 
						isset($actions[$method]) ? $actions[$method] :
							function($id = null) use ($method, $modelClass) {

								// Auto controller
								$controller = new Controller($this->document, $modelClass, $this->config);

								return $controller->{$method}($id);

							}
					);

				}

			});

			// Destroy objects
			unset($resource);
			unset($model);

		}
	}

}