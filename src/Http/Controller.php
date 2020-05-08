<?php

namespace HackerBoy\LaravelJsonApi\Http;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

use Art4\JsonApiClient\Helper\Parser;
use HackerBoy\JsonApi\Document;
use HackerBoy\LaravelJsonApi\Exceptions\JsonApiException;
use HackerBoy\LaravelJsonApi\Helpers\Authorization;
use HackerBoy\LaravelJsonApi\Helpers\ModelHelper;
use HackerBoy\LaravelJsonApi\Handlers\RelationHandler;

class Controller extends BaseController {

    use Traits\ExceptionHandler, Traits\Pagination, 
    Traits\CollectionRelationshipResolver, Traits\Store, 
    Traits\CustomQuery, Traits\DataFilter, Traits\Sortable;

    protected $document;
    protected $modelClass;
    protected $config;
    protected $request;

    public function __construct(Document $document, $modelClass, $config)
    {
        $this->document = $document;
        $this->modelClass = $modelClass;
        $this->config = $config;
        $this->request = Request::instance();
    }

    /**
     * Get request instance
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
    * Get a single resource
    * 
    * @param mixed Resource ID
    * @return Response
    */
    public function get($id, $asMethod = 'get', $successStatusCode = 200)
    {
        try {

            if ($id instanceof Model) {
                $resource = $id;
            } else {

                // Find resource
                $query = $this->modelClass::where((new $this->modelClass)->getKeyName(), $id);

                // Callback
                if (isset($this->config['events']['get.query']) and is_callable($this->config['events']['get.query'])) {
                    call_user_func_array($this->config['events']['get.query'], [$id, $this->modelClass, $query, $this]);
                }

                $resource = $query->first();

            }

            // Resource not found
            if (!$resource) {
                
                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Resource not found'
                    ],
                    'statusCode' => 404
                ]);

            }

            // Check
            Authorization::check('view', $resource);

            // Set data
            $this->document->setData($resource);

            // Resource instance
            $jsonApiResource = $this->document->getResource($resource);

            // Get relationships
            $relationships = $jsonApiResource->getRelationships();

            // Set included resources
            $includedData = [];

            // Optimize relationships
            foreach ($relationships as $relationshipName => $relationshipResourceData) {

                if (isset($this->config['resources'][$this->modelClass]['relationships'][$relationshipName]['included']) and !in_array($asMethod, $this->config['resources'][$this->modelClass]['relationships'][$relationshipName]['included'])) {
                    continue;
                }
                
                $_includedData = [];
                if (isset($relationshipResourceData['data'])) {
                    $_includedData = $relationshipResourceData['data'];
                } else {
                    $_includedData = $relationshipResourceData;
                }

                if (!($_includedData instanceof Model)) {
                    
                    // Optimize query
                    $this->resolveMixedCollectionRelationships($_includedData);

                }
                
                $this->mergeModelDataToArray($includedData, $_includedData);
            }

            // Remove unauthorized resources
            ModelHelper::removeUnauthorizedResources($includedData);

            // Add included data
            $this->document->addIncluded($includedData);

            // Callback
            if (isset($this->config['events']['get.beforeReturn']) and is_callable($this->config['events']['get.beforeReturn'])) {
                call_user_func_array($this->config['events']['get.beforeReturn'], [$resource, $this->document, $this]);
            }

            $data = $this->document->toArray();

            return response()->json($this->dataFilter($data), $successStatusCode);

        } catch (JsonApiException $e) {
            return $this->exceptionHandler($e);
        }
    }

    /**
    * Get a relationship data of a resource
    * 
    * @param mixed Resource ID
    * @param string Relationship name
    * @return Response
    */
    public function relationships($id, $relationshipName, $dataType = 'relationships')
    {
        try {

            if ($id instanceof Model) {
                $resource = $id;
            } else {

                // Find resource
                $query = $this->modelClass::where((new $this->modelClass)->getKeyName(), $id);

                // Callback
                if (isset($this->config['events']['get.query']) and is_callable($this->config['events']['get.query'])) {
                    call_user_func_array($this->config['events']['get.query'], [$id, $this->modelClass, $query, $this]);
                }

                $resource = $query->first();

            }

            // Resource not found
            if (!$resource) {
                
                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Resource not found'
                    ],
                    'statusCode' => 404
                ]);

            }

            // Pagination
            list($page) = $this->requestPagination();

            // Set relationship current page and limit (for relationship pagination)
            RelationHandler::setCurrentRelationshipPage($page);
            RelationHandler::setCustomRelationshipDataLimit($this->request->query('limit'));

            // Relationship not found
            $resourceInstance = $this->document->getResource($resource);
            $resourceRelationships = $resourceInstance->getRelationships();

            if (!array_key_exists($relationshipName, $resourceRelationships)) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Relationships not found'
                    ],
                    'statusCode' => 404
                ]);

            }

            // Get data as relation if relationship opimization was set
            if (isset($this->config['resources'][get_class($resource)]['relationships'][$relationshipName]['relation'])) {

                // Get data as relation query
                $relation = $resource->{$this->config['resources'][get_class($resource)]['relationships'][$relationshipName]['relation']}();

                // Sortable
                $data = [];
                $data['data'] = $this->sortQuery($relation, get_class($relation->getRelated()))->getResults();

                if ($relation instanceof RelationHandler) {
                    $data['links'] = $relation->getJsonApiLinks();
                }

            } else {
                $data = $resourceRelationships[$relationshipName];
            }

            // Set relationship data
            if (isset(((array) $data)['data'])) {
                $relationshipData = ((array) $data)['data'];
            } else {
                $relationshipData = $data;
            }

            // No results
            if (!$relationshipData or (is_countable($relationshipData) and !count($relationshipData))) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'No results'
                    ],
                    'statusCode' => 404
                ]);

            }

            if ($dataType === 'relationships') {
                $this->document->setData($relationshipData, $dataType);
            } else {

                // If not a single resource - optimize query
                if (!($relationshipData instanceof Model) and is_iterable($relationshipData)) {

                    // Get resource model class
                    $modelClass = null;

                    // Single resource
                    if ($relationshipData instanceof Model) {
                        $modelClass = get_class($relationshipData);
                    } elseif ($relationshipData instanceof Collection and $firstResource = $relationshipData->first()) { // Collection
                        $modelClass = get_class($firstResource);
                    } elseif (is_array($relationshipData) and isset($relationshipData[0])) { // Collection as array
                        $modelClass = get_class($relationshipData[0]);
                    }

                    // Cannot get model class
                    if (!$modelClass) {

                        throw new JsonApiException([
                            'errors' => [
                                'title' => 'Cannot get resource type'
                            ],
                            'statusCode' => 500
                        ]);

                    }

                    $this->modelClass = $modelClass;

                    // Return as collection
                    return $this->collection($relationshipData, 'relationshipData', isset($data['links']) ? $data['links'] : []);
                    
                } elseif ($relationshipData instanceof Model) { // Single resource

                    $this->modelClass = get_class($relationshipData);

                    // Return as single resource
                    return $this->get($relationshipData, 'relationshipData');

                } else {

                    throw new JsonApiException([
                        'errors' => [
                            'title' => 'Invalid resource type'
                        ],
                        'statusCode' => 400
                    ]);

                }
                

            }

            // If links member is set
            if (isset($data['links'])) {
                $this->document->setLinks($data['links']);
            }

            // Callback
            if (isset($this->config['events']['relationships.beforeReturn']) and is_callable($this->config['events']['relationships.beforeReturn'])) {
                call_user_func_array($this->config['events']['relationships.beforeReturn'], [$resource, $relationshipName, $this->document, $this]);
            }
            
            return response()->json($this->document);

        } catch (JsonApiException $e) {
            return $this->exceptionHandler($e);
        }
    }

    /**
    * Store relationship
    * 
    * @param mixed Resource ID
    * @param string Relationship name
    * @return Response
    */
    public function storeRelationships($id, $relationshipName)
    {
        try {

            // Find resource
            $query = $this->modelClass::where((new $this->modelClass)->getKeyName(), $id);

            // Callback
            if (isset($this->config['events']['get.query']) and is_callable($this->config['events']['get.query'])) {
                call_user_func_array($this->config['events']['get.query'], [$this->modelClass, $query, $this]);
            }

            $resource = $query->first();

            // Resource not found
            if (!$resource) {
                
                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Resource not found'
                    ],
                    'statusCode' => 404
                ]);

            }

            // Validate json-api data
            if (!$data = $this->request->input() or !Parser::isValidRequestString(json_encode($data))) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Invalid JSON:API data format'
                    ],
                    'statusCode' => 400
                ]);

            }

            // Relationship data
            $relationshipData = [
                $relationshipName => $data
            ];

            // Make relationship
            $this->makeRelationships($resource, $relationshipData, true);

            return $this->relationships($resource, $relationshipName, 'relationships');

        } catch (JsonApiException $e) {
            return $this->exceptionHandler($e);
        }
    }

    /**
    * Get a collection of resource
    * 
    * @param void
    * @return Response
    */
    public function collection($data = [], $asMethod = 'collection', $links = [], $meta = [])
    {
        try {

            // Check auth
            Authorization::check('viewAny', $this->modelClass);

            if (is_iterable($data) and $data) {
                $collection = $data;
            } else {

                // Make query
                $query = $this->modelClass::query();

                // Filter
                if ($filter = $this->request->query('filter') and is_array($filter)) {

                    $queryData = [
                    ];

                    foreach ($filter as $key => $value) {
                        $queryData[] = [
                            'field' => $key,
                            'value' => $value
                        ];
                    }

                    // Wrap the query composer
                    $query->where(function($query) use ($queryData) {
                        $this->queryComposer($queryData, $query);
                    });

                } elseif (($queryData = $this->request->query('_query') or $queryData = $this->request->header('query')) and $queryData = json_decode(urldecode($queryData), true) and is_array($queryData)) {

                    // Wrap the query composer
                    $query->where(function($query) use ($queryData) {
                        $this->queryComposer($queryData, $query);
                    });
                }

                // Callback
                if (isset($this->config['events']['collection.query']) and is_callable($this->config['events']['collection.query'])) {
                    call_user_func_array($this->config['events']['collection.query'], [$this->modelClass, $query, $this]);
                }

                // Pagination
                list($page, $limit, $skip) = $this->handlePagination();

                $collection = $this->sortQuery(clone $query)->take($limit)->skip($skip)->get();

                // Data pagination
                $this->responsePagination($query);

                // Callback
                if (isset($this->config['events']['collection.afterQuery']) and is_callable($this->config['events']['collection.afterQuery'])) {
                    call_user_func_array($this->config['events']['collection.afterQuery'], [$collection, $this]);
                }

                if (!$collection->count()) {
                    throw new JsonApiException([
                        'errors' => [
                            'title' => 'No results'
                        ],
                        'statusCode' => 404
                    ]);
                }

            }

            // Remove unauthorized resources
            ModelHelper::removeUnauthorizedResources($collection);

            // Set document data
            $this->document->setData($collection);

            // Resolve relationships
            $this->resolveCollectionRelationships($this->modelClass, $collection);

            // Add relationship resources to included
            $includedCollection = [];

            foreach ($collection as $resource) {

                $jsonApiResource = $this->document->getResource($resource);
                $resourceRelationships = $jsonApiResource->getRelationships();
                $resourceType = $jsonApiResource->getType();

                // Set included resources
                foreach ($resourceRelationships as $relationshipName => $relationshipResourceData) {

                    // If relationship isn't in included list
                    if (isset($this->config['resources'][$this->modelClass]['relationships'][$relationshipName]['included']) 
                        and !in_array($asMethod, $this->config['resources'][$this->modelClass]['relationships'][$relationshipName]['included'])
                    ) {
                        continue;
                    }

                    // If include_relationships is set
                    if ($includeRelationships = $this->request->query('include_relationships')
                        and is_array($includeRelationships)
                        and array_key_exists($resourceType, $includeRelationships)
                    ) {

                        $includeRelationshipNames = array_filter(explode(',', $includeRelationships[$resourceType]));

                        // If not in included list
                        if (!in_array($relationshipName, $includeRelationshipNames)) {
                            continue;
                        }

                    }

                    // If exclude_relationships is set
                    if ($excludeRelationships = $this->request->query('exclude_relationships')
                        and is_array($excludeRelationships)
                        and array_key_exists($resourceType, $excludeRelationships)
                    ) {

                        $excludeRelationshipNames = array_filter(explode(',', $excludeRelationships[$resourceType]));

                        // If in excluded list
                        if (in_array($relationshipName, $excludeRelationshipNames)) {
                            continue;
                        }

                    }

                    $relationshipResourceData = isset($relationshipResourceData['data']) ? $relationshipResourceData['data'] : $relationshipResourceData;

                    // Add included resources to mixed collection
                    $this->mergeModelDataToArray($includedCollection, $relationshipResourceData);
                    
                }
            }

            // Resolve included relationships
            $this->resolveMixedCollectionRelationships($includedCollection);

            // Remove unauthorized resources
            ModelHelper::removeUnauthorizedResources($includedCollection);

            // Add data to included collection
            $this->document->setIncluded($includedCollection);

            // Callback
            if (isset($this->config['events']['collection.beforeReturn']) and is_callable($this->config['events']['collection.beforeReturn'])) {
                call_user_func_array($this->config['events']['collection.beforeReturn'], [$collection, $this->document, $this]);
            }

            // Set links
            if ($links) {
                $this->document->setLinks($links);
            }

            // Set meta
            if ($meta) {
                $this->document->setMeta($meta);
            }

            $data = $this->document->toArray();

            return response()->json($this->dataFilter($data));

        } catch (JsonApiException $e) {
            return $this->exceptionHandler($e);
        }

    }

    public function post()
    {
        try {

            // Check auth
            Authorization::check('create', $this->modelClass);

            // Create new model object
            $resourceModel = new $this->modelClass;

            // Save resource
            $this->store($resourceModel, 'post');

            return $this->get($resourceModel, 'post', 201);

        } catch (JsonApiException $e) {
            return $this->exceptionHandler($e);
        }
    }

    public function patch($id)
    {
        try {

            // Find resource
            $query = $this->modelClass::where((new $this->modelClass)->getKeyName(), $id);

            // Callback
            if (isset($this->config['events']['patch.query']) and is_callable($this->config['events']['patch.query'])) {
                call_user_func_array($this->config['events']['patch.query'], [$this->modelClass, $query, $this]);
            }

            $resourceModel = $query->first();

            // Check auth
            Authorization::check('update', $resourceModel);

            if (!$resourceModel) {
                throw new JsonApiException([
                    'errors' => [
                        'title' => 'No results'
                    ],
                    'statusCode' => 404
                ]);
            }

            // Save resource
            $this->store($resourceModel, 'patch');

            return $this->get($resourceModel, 'patch');

        } catch (JsonApiException $e) {
            return $this->exceptionHandler($e);
        }

    }

    public function delete($id)
    {
        try {

            // Find resource
            $query = $this->modelClass::where((new $this->modelClass)->getKeyName(), $id);

            // Callback
            if (isset($this->config['events']['delete.query']) and is_callable($this->config['events']['delete.query'])) {
                call_user_func_array($this->config['events']['delete.query'], [$this->modelClass, $query, $this]);
            }

            $resourceModel = $query->first();

            if (!$resourceModel) {
                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Resource not found'
                    ],
                    'statusCode' => 404
                ]);
            }

            // Check auth
            Authorization::check('delete', $resourceModel);

            // Callback
            if (isset($this->config['events']['delete.deleting']) and is_callable($this->config['events']['delete.deleting'])) {
                call_user_func_array($this->config['events']['delete.deleting'], [$resourceModel, $this]);
            }

            $resourceModel->delete();

            // Callback
            if (isset($this->config['events']['delete.deleted']) and is_callable($this->config['events']['delete.deleted'])) {
                call_user_func_array($this->config['events']['delete.deleted'], [$resourceModel, $this]);
            }

            $this->document->setMeta([
                'status' => 'deleted'
            ]);

            // Callback
            if (isset($this->config['events']['delete.beforeReturn']) and is_callable($this->config['events']['delete.beforeReturn'])) {
                call_user_func_array($this->config['events']['delete.beforeReturn'], [$resourceModel, $this->document, $this]);
            }

            return response()->json($this->document);


        } catch (JsonApiException $e) {
            return $this->exceptionHandler($e);
        }
    }

}