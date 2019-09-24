<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Database\Eloquent\Relations\Relation;

use HackerBoy\LaravelJsonApi\Helpers\ModelHelper;

trait CollectionRelationshipResolver {

    /**
    * Resolve relationships of a mixed collection
    */
    protected function resolveMixedCollectionRelationships($collection)
    {
        $collectionByType = [];

        if (!is_iterable($collection)) {
            return;
        }

        foreach ($collection as $resource) {
            
            $modelClass = get_class($resource);

            if (!isset($collectionByType[$modelClass])) {
                $collectionByType[$modelClass] = [];
            }

            $collectionByType[$modelClass][] = $resource;

        }

        foreach ($collectionByType as $modelClass => $collection) {
            $this->resolveCollectionRelationships($modelClass, $collection);
        }
    }

    /**
    * Resolve relationships of a collection
    */
    protected function resolveCollectionRelationships($modelClass, $collection)
    {
        // Get relationship data
        $newModel = new $modelClass;

        // Clear all relationship properties to avoid unnecessary query
        if (isset($this->config['resources'][$modelClass]['relationships'])) {

            foreach ($this->config['resources'][$modelClass]['relationships'] as $relationshipData) {
                
                if (isset($relationshipData['relation'])) {
                    $newModel->{$relationshipData['relation']} = null;
                }

            }

        }

        $newResourceInstance = $this->document->getResource($newModel);
        $relationshipNames = array_keys($newResourceInstance->getRelationships());

        foreach ($relationshipNames as $relationshipName) {

            // If relationship name is defined in optimization config
            if (isset($this->config['resources'][$modelClass]['relationships'][$relationshipName]['relation'])
                and $relation = $this->config['resources'][$modelClass]['relationships'][$relationshipName]['relation']
                and method_exists($modelClass, $relation)
                and ($relationshipObject = $newModel->{$relation}()) instanceof Relation
            ) {

                // Check if this is relation handler
                if ($relationshipObject instanceof \HackerBoy\LaravelJsonApi\Handlers\RelationHandler) {
                    $relationshipObject = $relationshipObject->getRelation();
                }

                // Morph to one - go first because it's child class
                if ($relationshipObject instanceof Relations\MorphTo) {
                    $this->collectionMorphTo($collection, $relation, $relationshipObject);
                    continue;
                }

                // Morph to many
                if ($relationshipObject instanceof Relations\MorphToMany) {
                    $this->collectionMorphToMany($collection, $relation, $relationshipObject);
                    continue;
                }

                // Morph one
                if ($relationshipObject instanceof Relations\MorphOne) {
                    $this->collectionMorphOneOrMany($collection, $relation, $relationshipObject);
                    continue;
                }

                // Morph many
                if ($relationshipObject instanceof Relations\MorphMany) {
                    $this->collectionMorphOneOrMany($collection, $relation, $relationshipObject, true);
                    continue;
                }

                // Has one
                if ($relationshipObject instanceof Relations\HasOne) {
                    $this->collectionHasOne($collection, $relation, $relationshipObject);
                    continue;
                }

                // Has many
                if ($relationshipObject instanceof Relations\HasMany) {
                    $this->collectionHasMany($collection, $relation, $relationshipObject);
                    continue;
                }

                // Belongs to one
                if ($relationshipObject instanceof Relations\BelongsTo) {
                    $this->collectionBelongsTo($collection, $relation, $relationshipObject);
                    continue;
                }

                // Belongs to many
                if ($relationshipObject instanceof Relations\BelongsToMany) {
                    $this->collectionBelongsToMany($collection, $relation, $relationshipObject);
                    continue;
                }

                // Has one through
                if ($relationshipObject instanceof Relations\HasOneThrough) {
                    $this->collectionHasOneThrough($collection, $relation, $relationshipObject, $newModel);
                    continue;
                }

                // Has many through
                if ($relationshipObject instanceof Relations\HasManyThrough) {
                    $this->collectionHasManyThrough($collection, $relation, $relationshipObject, $newModel);
                    continue;
                }

            }

        }

        unset($newResourceInstance);
        unset($newModel);
    }

    /**
    * Optimize morphTo relationship
    */
    protected function collectionMorphTo($collection, $relation, $relationshipObject)
    {
        $morphMap = $relationshipObject->morphMap();

        // Collection by group
        $group = [];

        foreach ($collection as $resource) {

            // Type is null
            if (!$resource->{$relationshipObject->getMorphType()}) {
                $resource->{$relation} = null;
                continue;
            }

            $morphToModelClass = $resource->{$relationshipObject->getMorphType()};

            // Mapped name
            if (isset($morphMap[$morphToModelClass])) {
                $morphToModelClass = $morphMap[$morphToModelClass];
            }

            // Group it
            if (!isset($group[$morphToModelClass])) {
                $group[$morphToModelClass] = [];
            }

            $group[$morphToModelClass][] = $resource;

        }

        // Group query
        foreach ($group as $morphToModelClass => $resourceGroup) {

            // Get list resource ids
            $morphToResourceIds = ModelHelper::getAttributeValues($resourceGroup, $relationshipObject->getForeignKeyName());

            // Get key name
            $morphToResourceInstance = new $morphToModelClass;
            $morphToResourceKeyName = $morphToResourceInstance->getKeyName();
            unset($morphToResourceInstance);

            // Get morphToResources
            $morphToResources = $morphToModelClass::whereIn($morphToResourceKeyName, $morphToResourceIds)->get();

            // Attach to resource
            foreach ($resourceGroup as $resource) {
                $resource->{$relation} = $morphToResources->where($morphToResourceKeyName, $resource->{$relationshipObject->getForeignKeyName()})->first();
            }
        }
    }

    /**
    * Optimize morphToMany relationship
    */
    protected function collectionMorphToMany($collection, $relation, $relationshipObject)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);
        
        // Remove first where
        unset($relationshipQuery->wheres[0]);

        // Get resource ids
        $collectionIds = ModelHelper::getAttributeValues($collection, $relationshipObject->getParent()->getKeyName());

        // Modify query
        $relationshipQuery->whereIn($relationshipObject->getQualifiedForeignPivotKeyName(), $collectionIds);

        // Get results
        $results = $relationshipObject->get();

        // Attach results to resources
        $pivot = $relationshipObject->getPivotAccessor();
        foreach ($collection as $resource) {

            $relatedResources = [];
            foreach ($results as $relatedResource) {
                    
                if ($relatedResource->{$pivot}->{$relationshipObject->getForeignPivotKeyName()} === $resource->{$resource->getKeyName()}) {
                    $relatedResources[] = $relatedResource;
                }

            }

            $resource->{$relation} = collect($relatedResources);
        }

    }

    /**
    * Optimize morphOne relationship
    */
    protected function collectionMorphOneOrMany($collection, $relation, $relationshipObject, $isMany = false)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);

        // Remove the first where
        unset($relationshipQuery->wheres[0]);

        // Get resource ids
        $resourceIds = ModelHelper::getAttributeValues($collection, $relationshipObject->getParent()->getKeyName());

        // Modify query
        $relationshipQuery->whereIn($relationshipObject->getQualifiedForeignKeyName(), $resourceIds);

        // Get results
        $results = $relationshipObject->get();

        // Attach results to resources
        foreach ($collection as $resource) {

            $related = $results->where($relationshipObject->getForeignKeyName(), $resource->{$resource->getKeyName()});

            if (!$isMany) {
                $related = $related->first();
            }

            $resource->{$relation} = $related;
        }
    }

    /**
    * Optimize hasOne relationship
    */
    protected function collectionHasOne($collection, $relation, $relationshipObject)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);

        // Remove first where
        unset($relationshipQuery->wheres[0]);

        // Get collection IDs
        $collectionIds = ModelHelper::getAttributeValues($collection, $relationshipObject->getParent()->getKeyName());

        // Add query
        $relationshipQuery->whereIn($relationshipObject->getQualifiedForeignKeyName(), $collectionIds);
        $relationshipQuery->groupBy($relationshipObject->getQualifiedForeignKeyName());

        $results = $relationshipObject->get();

        // Attach result to resource relation
        foreach ($collection as $resource) {
            $resource->{$relation} = $results->where($relationshipObject->getForeignKeyName(), $resource->{$relationshipObject->getParent()->getKeyName()})->first();
        }
    }

    /**
    * Optimize hasMany relationship
    */
    protected function collectionHasMany($collection, $relation, $relationshipObject)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);

        // Remove first where
        unset($relationshipQuery->wheres[0]);

        // Get collection IDs
        $collectionIds = ModelHelper::getAttributeValues($collection, $relationshipObject->getParent()->getKeyName());

        // Add query
        $relationshipQuery->whereIn($relationshipObject->getQualifiedForeignKeyName(), $collectionIds);

        $results = $relationshipObject->get();

        // Attach result to resource relation
        foreach ($collection as $resource) {
            $resource->{$relation} = $results->where($relationshipObject->getForeignKeyName(), $resource->{$relationshipObject->getParent()->getKeyName()});
        }
    }

    /**
    * Optimize belongsTo relationship
    */
    protected function collectionBelongsTo($collection, $relation, $relationshipObject)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);

        // Remove first where
        unset($relationshipQuery->wheres[0]);

        // Get parent foreign key values
        $relatedIds = ModelHelper::getAttributeValues($collection, $relationshipObject->getForeignKeyName());

        // Add query
        $relationshipQuery->whereIn($relationshipObject->getQualifiedOwnerKeyName(), $relatedIds);

        // Get results
        $results = $relationshipObject->get();

        // Attach result to resource relation
        foreach ($collection as $resource) {
            $resource->{$relation} = $results->where($relationshipObject->getOwnerKeyName(), $resource->{$relationshipObject->getForeignKeyName()})->first();
        }
    }

    /**
    * Optimize belongsToMany relationships
    */
    protected function collectionBelongsToMany($collection, $relation, $relationshipObject)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);

        // Remove first where
        unset($relationshipQuery->wheres[0]);

        // Get parent ids
        $parentIds = ModelHelper::getAttributeValues($collection, $relationshipObject->getParent()->getKeyName());

        $relationshipQuery->whereIn($relationshipObject->getForeignPivotKeyName(), $parentIds);

        // Get results
        $results = $relationshipObject->get();

        // Attach result to resource relation
        $resultsById = [];
        $pivot = $relationshipObject->getPivotAccessor();
        foreach ($results as $related) {

            if (!isset($resultsById[$related->{$pivot}->{$relationshipObject->getForeignPivotKeyName()}])) {
                $resultsById[$related->{$pivot}->{$relationshipObject->getForeignPivotKeyName()}] = [];
            }

            $resultsById[$related->{$pivot}->{$relationshipObject->getForeignPivotKeyName()}][] = $related;

        }

        foreach ($collection as $resource) {
            $resource->{$relation} = isset($resultsById[$resource->{$resource->getKeyName()}]) ? $resultsById[$resource->{$resource->getKeyName()}] : [];
            $resource->{$relation} = collect($resource->{$relation});
        }
    }

    /**
    * Optimize hasOneThrough relationships
    */
    protected function collectionHasOneThrough($collection, $relation, $relationshipObject, $newModel)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);

        // Get collection ids
        $collectionIds = ModelHelper::getAttributeValues($collection, $newModel->getKeyName());

        // Remove first where
        unset($relationshipQuery->wheres[0]);

        $relationshipQuery->whereIn($relationshipObject->getQualifiedFirstKeyName(), $collectionIds);

        $results = $relationshipObject->get();

        // Attach result to resource relation
        foreach ($collection as $resource) {
            $resource->{$relation} = $results->where('laravel_through_key', $resource->{$resource->getKeyName()})->first();
        }

    }

    /**
    * Optimize hasManyThrough relationships
    */
    protected function collectionHasManyThrough($collection, $relation, $relationshipObject, $newModel)
    {
        $relationshipQuery = $this->getQuery($relationshipObject);

        // Get collection ids
        $collectionIds = ModelHelper::getAttributeValues($collection, $newModel->getKeyName());

        // Remove first where
        unset($relationshipQuery->wheres[0]);

        $relationshipQuery->whereIn($relationshipObject->getQualifiedFirstKeyName(), $collectionIds);

        $results = $relationshipObject->get();

        // Attach result to resource relation
        foreach ($collection as $resource) {
            $resource->{$relation} = $results->where('laravel_through_key', $resource->{$resource->getKeyName()});
        }

    }

    protected function mergeModelDataToArray(&$array, $modelData)
    {
        if ($modelData instanceof Model) {
            $array[] = $modelData;
        } elseif (is_iterable($modelData)) {
            
            foreach ($modelData as $model) {
                $this->mergeModelDataToArray($array, $model);
            }

        }
    }

    private function getQuery($relationshipObject)
    {
        return $relationshipObject->getQuery()->getQuery();
    }

}