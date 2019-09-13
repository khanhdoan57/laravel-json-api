<?php

namespace HackerBoy\LaravelJsonApi\Helpers;

class JsonApi {

    protected static $data = [];

    public static function getModelClassByResourceType($resourceType)
    {
        foreach (self::$data as $modelClass => $_resourceType) {
                
            if ($resourceType === $_resourceType) {
                return $modelClass;
            }

        }

        return null;
    }

    public static function getResourceTypeByModelClass($modelClass)
    {
        if (!isset(self::$data[$modelClass])) {
            return null;
        }

        return self::$data[$modelClass];
    }

    public static function load($document, $jsonApiConfig)
    {
        if (!isset($jsonApiConfig['resource_map'])) {
            return;
        }

        foreach ($jsonApiConfig['resource_map'] as $modelClass => $resourceClass) {
            
            $newModelInstance = new $modelClass;
            $resourceInstance = new $resourceClass($newModelInstance, $document);

            self::$data[$modelClass] = $resourceInstance->getType();

            unset($resourceInstance);
            unset($newModelInstance);

        }
    }
}