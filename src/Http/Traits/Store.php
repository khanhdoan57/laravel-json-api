<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

use Art4\JsonApiClient\Helper\Parser;
use Illuminate\Support\Facades\Validator;
use HackerBoy\LaravelJsonApi\Exceptions\JsonApiException;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Database\Eloquent\Relations\Relation;

use HackerBoy\LaravelJsonApi\Helpers as Helper;
use HackerBoy\LaravelJsonApi\Helpers\Authorization;

trait Store {

    /**
    * Save object, create or update
    *
    * @param Model Resource model
    * @param string
    * @return Model
    */
    protected function store($resourceModel, $asMethod = 'post')
    {
        // Protect data by a transaction
        \DB::transaction(function() use ($resourceModel, $asMethod) {

            // Get fillable setting
            $fillable = @$this->config['resources'][$this->modelClass]['fillable'];
            $fillable = $fillable ? $fillable : $resourceModel->getFillable();

            if (!is_array($fillable)) {
                
                throw new JsonApiException([
                    'errors' => [
                        'title' => '$fillable must be an array'
                    ],
                    'statusCode' => 500
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

            // Validate resource type
            $resourceInstance = $this->document->getResource($resourceModel);
            $resourceType = $resourceInstance->getType($resourceModel);

            if ($data['data']['type'] !== $resourceType) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Resource type must be: '.$resourceType
                    ],
                    'statusCode' => 400
                ]);

            }

            // Attributes
            $attributes = @$data['data']['attributes'];

            if (!$attributes) {
                $attributes = [];
            }

            // Validate attriutes not containing malicious fields
            foreach (array_keys($attributes) as $attributeKey) {
                
                // Un-accepted attribute
                if (!in_array($attributeKey, $fillable)) {

                    throw new JsonApiException([
                        'errors' => [
                            'title' => 'Attribute: '.$attributeKey.' is not allowed for this method'
                        ],
                        'statusCode' => 400
                    ]);

                }

            }

            // Field validator
            $validationData = @$this->config['resources'][$this->modelClass]['validation'][$asMethod];

            if (is_array($validationData) and count($attributes)) {

                $validator = Validator::make($attributes, $validationData);

                // Validation fails
                if ($validator->fails()) {

                    // Error data
                    $errors = [];

                    foreach ($validator->errors()->all() as $message) {
                        $errors[] = [
                            'title' => $message
                        ];
                    }

                    // Return error
                    throw new JsonApiException([
                        'errors' => $errors,
                        'statusCode' => 400
                    ]);
                }

            }
            
            foreach ($attributes as $key => $value) {
                $resourceModel->{$key} = $value;
            }

            // Callback
            if (isset($this->config['events'][$asMethod.'.saving']) and is_callable($this->config['events'][$asMethod.'.saving'])) {
                call_user_func($this->config['events'][$asMethod.'.saving'], $resourceModel);
            }

            $resourceModel->save();

            // Callback
            if (isset($this->config['events'][$asMethod.'.saved']) and is_callable($this->config['events'][$asMethod.'.saved'])) {
                call_user_func($this->config['events'][$asMethod.'.saved'], $resourceModel);
            }

            // Relationships handler
            if (isset($data['data']['relationships'])) {
                $this->makeRelationships($resourceModel, $data['data']['relationships']);
            }

            return $resourceModel;

        });
            
    }

    /**
    * Save relationships
    *
    * @param Model Resource model
    * @param array data
    * @return void
    */
    protected function makeRelationships($resourceModel, $relationshipData, $isRelationshipRequest = false)
    {
        // DB transaction
        \DB::transaction(function() use ($resourceModel, $relationshipData, $isRelationshipRequest) {

            $modelClass = get_class($resourceModel);

            foreach ($relationshipData as $relationshipName => $relationshipData) {

                if (array_key_exists('write', ($this->config['resources'][$modelClass]['relationships'][$relationshipName]))) {

                    // If write option is off
                    if (!$this->config['resources'][$modelClass]['relationships'][$relationshipName]['write']) {
                        continue;
                    }

                    // Custom handler
                    if (is_callable($this->config['resources'][$modelClass]['relationships'][$relationshipName]['write'])) {
                        call_user_func_array($this->config['resources'][$modelClass]['relationships'][$relationshipName]['write'], [$resourceModel, $relationshipData]);
                        continue;
                    }
                    
                }
                
                // Auto handler
                if (isset($this->config['resources'][$modelClass]['relationships'][$relationshipName]['property'])
                    and $property = $this->config['resources'][$modelClass]['relationships'][$relationshipName]['property']
                    and method_exists($modelClass, $property)
                    and (($relationshipObject = $resourceModel->{$property}()) instanceof Relation)
                ) {

                    // Check if this is relation handler
                    if ($relationshipObject instanceof \HackerBoy\LaravelJsonApi\Handlers\RelationHandler) {
                        $relationshipObject = $relationshipObject->getRelation();
                    }

                    // Morph to one - go first because it's child class
                    if ($relationshipObject instanceof Relations\MorphTo) {

                        // Support only patch
                        if ($isRelationshipRequest and !$this->request->isMethod('patch')) {
                            throw new JsonApiException([
                                'errors' => [
                                    'title' => 'Request method '.$this->request->method().' is not supported'
                                ],
                                'statusCode' => 403
                            ]);
                        }

                        $this->morphToRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData);
                        continue;
                    }

                    // Morph and belongs to many
                    if ($relationshipObject instanceof Relations\BelongsToMany) {

                        // Support only patch
                        if ($isRelationshipRequest) {

                            if (!in_array($this->request->method(), ['POST', 'PATCH', 'DELETE'])) {

                                throw new JsonApiException([
                                    'errors' => [
                                        'title' => 'Request method '.$this->request->method().' is not supported'
                                    ],
                                    'statusCode' => 403
                                ]);

                            }

                            // Append relationship - not replacing
                            if ($this->request->isMethod('post')) {
                                $this->belongsToManyRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData, false);
                                continue;
                            }

                            // Delete relationship
                            if ($this->request->isMethod('delete')) {
                                $this->belongsToManyRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData, 'delete');
                                continue;
                            }
                            
                        }

                        $this->belongsToManyRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData, 'replace');
                        continue;
                    }

                    // Morph one
                    if ($relationshipObject instanceof Relations\MorphOne) {

                        // Support only patch
                        if ($isRelationshipRequest and !$this->request->isMethod('patch')) {
                            throw new JsonApiException([
                                'errors' => [
                                    'title' => 'Request method '.$this->request->method().' is not supported'
                                ],
                                'statusCode' => 403
                            ]);
                        }

                        $this->morphOneOrManyRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData);
                        continue;
                    }

                    // Morph many
                    if ($relationshipObject instanceof Relations\MorphMany) {

                        // Support only post
                        if ($isRelationshipRequest and !$this->request->isMethod('post')) {
                            throw new JsonApiException([
                                'errors' => [
                                    'title' => 'Request method '.$this->request->method().' is not defined.'
                                ],
                                'statusCode' => 403
                            ]);
                        }

                        $this->morphOneOrManyRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData, true);
                        continue;
                    }

                    // Has one
                    if ($relationshipObject instanceof Relations\HasOne) {

                        // Support only patch
                        if ($isRelationshipRequest and !$this->request->isMethod('patch')) {
                            throw new JsonApiException([
                                'errors' => [
                                    'title' => 'Request method '.$this->request->method().' is not supported'
                                ],
                                'statusCode' => 403
                            ]);
                        }

                        $this->hasOneOrManyRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData, false);
                        continue;
                    }

                    // Has many
                    if ($relationshipObject instanceof Relations\HasMany) {

                        // Support only post
                        if ($isRelationshipRequest and !$this->request->isMethod('post')) {
                            throw new JsonApiException([
                                'errors' => [
                                    'title' => 'Request method '.$this->request->method().' is not defined.'
                                ],
                                'statusCode' => 403
                            ]);
                        }

                        $this->hasOneOrManyRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData, true);
                        continue;
                    }

                    // Belongs to one
                    if ($relationshipObject instanceof Relations\BelongsTo) {

                        // Support only patch
                        if ($isRelationshipRequest and !$this->request->isMethod('patch')) {
                            throw new JsonApiException([
                                'errors' => [
                                    'title' => 'Request method '.$this->request->method().' is not supported'
                                ],
                                'statusCode' => 403
                            ]);
                        }

                        $this->belongsToRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData);
                        continue;
                    }

                    // Has one through
                    if ($relationshipObject instanceof Relations\HasOneThrough) {
                        $this->hasOneOrManyThroughRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData);
                        continue;
                    }

                    // Has many through
                    if ($relationshipObject instanceof Relations\HasManyThrough) {
                        $this->hasOneOrManyThroughRelationshipMaker($resourceModel, $relationshipObject, $relationshipName, $relationshipData, true);
                        continue;
                    }

                    throw new JsonApiException([
                        'errors' => [
                            'title' => 'No handler found for relationship '.$relationshipName
                        ],
                        'statusCode' => 500
                    ]);

                }

            }

        });
        
    }

    /**
    * MorphTo relationship maker
    *
    * @param Model Model object
    * @param Relation Relationship object
    * @param string Relationship name
    * @param array Relationship data
    * @return voild
    */
    protected function morphToRelationshipMaker($modelObject, $relationshipObject, $relationshipName, $relationshipData)
    {
        if (!isset($relationshipData['data'])) {
            return false;
        }

        if (!isset($relationshipData['data']['id'])) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid data format for relationship '.$relationshipName.'. Relationship data must be an object'
                ],
                'statusCode' => 400
            ]);

        }

        $morphMap = $relationshipObject->morphMap();

        // Find morph to class name
        $morphToModelClass = Helper\JsonApi::getModelClassByResourceType($relationshipData['data']['type']);

        if (!$morphToModelClass) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid resource type for relationship '.$relationshipName
                ],
                'statusCode' => 400
            ]);

        }

        // Find morph to instance
        $morphToModelObject = $morphToModelClass::find($relationshipData['data']['id']);

        if (!$morphToModelObject) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid relationship data ('.$relationshipName.'). Relationship resource '.$relationshipData['data']['type'].' (ID: '.$relationshipData['data']['id'].') is not found'
                ],
                'statusCode' => 400
            ]);

        }

        // If has name map
        if (isset($morphMap[$morphToModelClass])) {
            $morphToModelClass = $morphMap[$morphToModelClass];
        }

        // Set morph type and id
        $modelObject->{$relationshipObject->getMorphType()} = $morphToModelClass;
        $modelObject->{$relationshipObject->getForeignKeyName()} = $relationshipData['data']['id'];

        // Callback
        if (isset($this->config['events']['relationships.saving']) and is_callable($this->config['events']['relationships.saving'])) {
            call_user_func_array($this->config['events']['relationships.saving'], [$modelObject, $relationshipName, $relationshipData]);
        }

        // Save without observer events
        $this->saveRelationshipModel($modelObject, $relationshipName);

        // Callback
        if (isset($this->config['events']['relationships.saved']) and is_callable($this->config['events']['relationships.saved'])) {
            call_user_func_array($this->config['events']['relationships.saved'], [$modelObject, $relationshipName, $relationshipData]);
        }

    }

    /**
    * MorphToMany relationship maker
    *
    * @param Model Model object
    * @param Relation Relationship object
    * @param string Relationship name
    * @param array Relationship data
    * @return voild
    */
    protected function belongsToManyRelationshipMaker($modelObject, $relationshipObject, $relationshipName, $relationshipData, $type = 'replace')
    {
        if (!isset($relationshipData['data'])) {
            return;
        }

        if ((!is_array($relationshipData['data']) or isset($relationshipData['data']['id'])) and $relationshipData['data'] !== null) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid data format for relationship '.$relationshipName.'. Relationship data must be an array'
                ],
                'statusCode' => 400
            ]);

        }

        $parentClass = get_class($modelObject);
        $relatedClass = get_class($relationshipObject->getRelated());
        $relatedType = Helper\JsonApi::getResourceTypeByModelClass($relatedClass);

        $morphMap = $relationshipObject->morphMap();

        $morphToType = ($relationshipObject instanceof Relations\MorphToMany) ? $relationshipObject->getMorphClass() : $parentClass;

        if (isset($morphMap[$morphToType])) {
            $morphToType = $morphMap[$morphToType];
        }

        // Get relationship table name
        $relationshipTable = $relationshipObject->getTable();

        // Delete all current relationship first - for replacing
        if ($type === 'replace') {

            $deleteQuery = \DB::table($relationshipTable)
                ->where($relationshipObject->getQualifiedForeignPivotKeyName(), $modelObject->{$modelObject->getKeyName()});

            if ($relationshipObject instanceof Relations\MorphToMany) {
                $deleteQuery->where($relationshipObject->getMorphType(), $morphToType);
            }

            $deleteQuery->delete();
        }

        // Data is null - return
        if (!$relationshipData['data']) {
            return;
        }

        $insertData = [];
        foreach ($relationshipData['data'] as $relationship) {
                
            if ($relationship['type'] !== $relatedType) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Invalid resource type ('.$relationship['type'].') for relationship '.$relationshipName.'. Resource type must be: '.$relatedType
                    ],
                    'statusCode' => 400
                ]);

            }

            // Check related resource exists
            if (!$relatedModelObject = $relatedClass::find($relationship['id'])) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Relationship resource '.$relatedType.' (ID: '.$relationship['id'].') is not found'
                    ],
                    'statusCode' => 400
                ]);

            }

            // Check permission
            Authorization::check('update', $relatedModelObject);
            Authorization::check('update', $modelObject);
            
            $_insertData = [
                $relationshipObject->getForeignPivotKeyName() => $modelObject->{$modelObject->getKeyName()},
                $relationshipObject->getRelatedPivotKeyName() => $relationship['id']
            ];

            if ($relationshipObject instanceof Relations\MorphToMany) {
                $_insertData[$relationshipObject->getMorphType()] = $morphToType;
            }

            // If not replacing
            if (!$type !== 'replace') { 

                // Find existing
                $query = \DB::table($relationshipTable);

                foreach ($_insertData as $key => $value) {
                    $query->where($key, $value);
                }

                // If deleting
                if ($type === 'delete') {
                    $query->delete();
                    continue;
                }

                $find = $query->first();

                // Relationship already exists - skip
                if ($find) {
                    continue;
                }
                
            }

            // Append created at
            if ($relationshipObject->createdAt() and $relationshipObject->hasPivotColumn($relationshipObject->createdAt())) {
                $_insertData[$relationshipObject->createdAt()] = \Carbon\Carbon::now();
            }

            // Append updated at
            if ($relationshipObject->updatedAt() and $relationshipObject->hasPivotColumn($relationshipObject->updatedAt())) {
                $_insertData[$relationshipObject->updatedAt()] = \Carbon\Carbon::now();
            }

            $insertData[] = $_insertData;

        }

        // Insert data
        if (count($insertData)) {

            // Callback
            if (isset($this->config['events']['relationships.saving']) and is_callable($this->config['events']['relationships.saving'])) {
                call_user_func_array($this->config['events']['relationships.saving'], [$modelObject, $relationshipName, $relationshipData]);
            }

            // Create relationship
            \DB::table($relationshipTable)->insert($insertData);

            // Callback
            if (isset($this->config['events']['relationships.saved']) and is_callable($this->config['events']['relationships.saved'])) {
                call_user_func_array($this->config['events']['relationships.saved'], [$modelObject, $relationshipName, $relationshipData]);
            }

        }

    }

    /**
    * MorphOne relationship maker
    *
    * @param Model Model object
    * @param Relation Relationship object
    * @param string Relationship name
    * @param array Relationship data
    * @param bool Is many?
    * @return voild
    */
    protected function morphOneOrManyRelationshipMaker($modelObject, $relationshipObject, $relationshipName, $relationshipData, $isMany = false)
    {
        if (!isset($relationshipData['data'])) {
            return;
        }

        // Many - data must be array
        if ($isMany and !is_array($relationshipData['data'])) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid data format for relationship '.$relationshipName.'. Relationship data must be an array'
                ],
                'statusCode' => 400
            ]);

        }

        // One - data must be object
        if (!$isMany and !isset($relationshipData['data']['id'])) {
            
            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid data format for relationship '.$relationshipName.'. Relationship data must be an object'
                ],
                'statusCode' => 400
            ]);

        }

        // Validate data type
        $relatedClass = get_class($relationshipObject->getRelated());
        $relatedType = Helper\JsonApi::getResourceTypeByModelClass($relatedClass);

        // Morph type
        $morphMap = $relationshipObject->morphMap();
        $morphToType = get_class($modelObject);

        if (isset($morphMap[$morphToType])) {
            $morphToType = $morphMap[$morphToType];
        }

        $morphOne = function($relationship) use ($modelObject, $relationshipObject, $relatedClass, $relatedType, $relationshipName, $relationshipData, $morphToType) {

            // Invalid type
            if ($relatedType !== $relationship['type']) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Invalid resource type ('.$relationship['type'].') for relationship '.$relationshipName.'. Resource type must be: '.$relatedType
                    ],
                    'statusCode' => 400
                ]);

            }

            // Resource not found
            if (!$relatedModelObject = $relatedClass::find($relationship['id'])) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Relationship resource '.$relatedType.' (ID: '.$relationship['id'].') is not found'
                    ],
                    'statusCode' => 400
                ]);

            }

            // Update it
            $relatedModelObject->{$relationshipObject->getForeignKeyName()} = $modelObject->{$modelObject->getKeyName()};
            $relatedModelObject->{$relationshipObject->getMorphType()} = $morphToType;

            // Callback
            if (isset($this->config['events']['relationships.saving']) and is_callable($this->config['events']['relationships.saving'])) {
                call_user_func_array($this->config['events']['relationships.saving'], [$modelObject, $relationshipName, $relationshipData]);
            }

            $this->saveRelationshipModel($relatedModelObject, $relationshipName);

            // Callback
            if (isset($this->config['events']['relationships.saved']) and is_callable($this->config['events']['relationships.saved'])) {
                call_user_func_array($this->config['events']['relationships.saved'], [$modelObject, $relationshipName, $relationshipData]);
            }

        };

        if (!$isMany) {
            return $morphOne($relationshipData['data']);
        }

        foreach ($relationshipData['data'] as $_relationshipData) {
            $morphOne($_relationshipData);
        }

        return;
    }

    /**
    * hasOne relationship maker
    *
    * @param Model Model object
    * @param Relation Relationship object
    * @param string Relationship name
    * @param array Relationship data
    * @return voild
    */
    protected function hasOneOrManyRelationshipMaker($modelObject, $relationshipObject, $relationshipName, $relationshipData, $isMany = false)
    {
        if (!isset($relationshipData['data'])) {
            return false;
        }

        if (!$isMany and !isset($relationshipData['data']['id'])) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid data format for relationship '.$relationshipName.'. Relationship data must be an object'
                ],
                'statusCode' => 400
            ]);

        }

        $relatedClass = get_class($relationshipObject->getRelated());
        $relatedType = Helper\JsonApi::getResourceTypeByModelClass($relatedClass);

        $hasOneRelationshipMaker = function($relationship) use ($modelObject, $relationshipObject, $relationshipName, $relationshipData, $relatedClass, $relatedType) {

            // Check type
            if ($relationship['type'] !== $relatedType) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Invalid resource type ('.$relationship['type'].') for relationship '.$relationshipName.'. Resource type must be: '.$relatedType
                    ],
                    'statusCode' => 400
                ]);

            }

            // Check related object exists
            if (!$find = $relatedClass::find($relationship['id'])) {

                throw new JsonApiException([
                    'errors' => [
                        'title' => 'Relationship resource '.$relatedType.' (ID: '.$relationship['id'].') is not found'
                    ],
                    'statusCode' => 400
                ]);

            }

            // Save relationship
            $find->{$relationshipObject->getForeignKeyName()} = $modelObject->{$modelObject->getKeyName()};

            // Callback
            if (isset($this->config['events']['relationships.saving']) and is_callable($this->config['events']['relationships.saving'])) {
                call_user_func_array($this->config['events']['relationships.saving'], [$modelObject, $relationshipName, $relationshipData]);
            }

            $this->saveRelationshipModel($find, $relationshipName);

            // Callback
            if (isset($this->config['events']['relationships.saved']) and is_callable($this->config['events']['relationships.saved'])) {
                call_user_func_array($this->config['events']['relationships.saved'], [$modelObject, $relationshipName, $relationshipData]);
            }

        };

        if (!$isMany) {
            $hasOneRelationshipMaker($relationshipData['data']);
            return;
        }

        foreach ($relationshipData['data'] as $relationship) {
            $hasOneRelationshipMaker($relationship);
        }

        return;
    }

    /**
    * belongsTo relationship maker
    *
    * @param Model Model object
    * @param Relation Relationship object
    * @param string Relationship name
    * @param array Relationship data
    * @return voild
    */
    protected function belongsToRelationshipMaker($modelObject, $relationshipObject, $relationshipName, $relationshipData, $isMany = false)
    {
        if (!isset($relationshipData['data'])) {
            return false;
        }

        if (!isset($relationshipData['data']['id'])) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid data format for relationship '.$relationshipName.'. Relationship data must be an object'
                ],
                'statusCode' => 400
            ]);

        }

        $relatedClass = get_class($relationshipObject->getRelated());
        $relatedType = Helper\JsonApi::getResourceTypeByModelClass($relatedClass);

        // Check type
        if ($relationshipData['data']['type'] !== $relatedType) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Invalid resource type ('.$relationshipData['data']['type'].') for relationship '.$relationshipName.'. Resource type must be: '.$relatedType
                ],
                'statusCode' => 400
            ]);

        }

        // Check related object exists
        if (!$find = $relatedClass::find($relationshipData['data']['id'])) {

            throw new JsonApiException([
                'errors' => [
                    'title' => 'Relationship resource '.$relatedType.' (ID: '.$relationshipData['data']['id'].') is not found'
                ],
                'statusCode' => 400
            ]);

        }

        // Callback
        if (isset($this->config['events']['relationships.saving']) and is_callable($this->config['events']['relationships.saving'])) {
            call_user_func_array($this->config['events']['relationships.saving'], [$modelObject, $relationshipName, $relationshipData]);
        }

        // Update
        $modelObject->{$relationshipObject->getForeignKeyName()} = $find->{$find->getKeyName()};
        $this->saveRelationshipModel($modelObject, $relationshipName);

        // Callback
        if (isset($this->config['events']['relationships.saved']) and is_callable($this->config['events']['relationships.saved'])) {
            call_user_func_array($this->config['events']['relationships.saved'], [$modelObject, $relationshipName, $relationshipData]);
        }
    }

    /**
    * hasOneThrough or hasManyThrough relationship maker
    *
    * @param Model Model object
    * @param Relation Relationship object
    * @param string Relationship name
    * @param array Relationship data
    * @param bool Is many?
    * @return voild
    */
    protected function hasOneOrManyThroughRelationshipMaker($modelObject, $relationshipObject, $relationshipName, $relationshipData, $isMany = false)
    {
        throw new JsonApiException([
            'errors' => [
                'title' => 'This request method is not available'
            ],
            'statusCode' => 403
        ]);
    }

    /**
    * Save a model without triggering events
    */
    protected function saveRelationshipModel($modelObject, $relationshipName)
    {
        // Check permission
        Authorization::check($modelObject->exists ? 'update' : 'create', $modelObject);

        // If not using observer
        if (isset($this->config['resources'][get_class($modelObject)]['relationships'][$relationshipName]['use_observers'])
            and !$this->config['resources'][get_class($modelObject)]['relationships'][$relationshipName]['use_observers']
        ) {

            $dispatcher = $modelObject->getEventDispatcher();
            $modelObject->unsetEventDispatcher();
            $save = $modelObject->save();
            $modelObject->setEventDispatcher($dispatcher);

            return $save;

        }

        // Save normally
        return $modelObject->save();
        
    }
}