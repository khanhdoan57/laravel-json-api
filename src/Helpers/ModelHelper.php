<?php

namespace HackerBoy\LaravelJsonApi\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ModelHelper {

    public static function getAttributeValues($collection, $key = 'id')
    {
        $attributes = [];

        foreach ($collection as $o) {
            $attributes[] = $o->{$key};
        }

        return array_unique(array_filter($attributes));
    }

    public static function removeUnauthorizedResources(&$collection)
    {
        // Sort by class
        $sortByClass = [];

        $finalData = [];

        foreach ($collection as $modelObject) {
            
            $className = get_class($modelObject);

            if (!isset($sortByClass[$className])) {
                $sortByClass[$className] = [];
            }

            $sortByClass[$className][] = $modelObject;

        }

        foreach ($sortByClass as $className => $data) {
    
            // Done have permission (if deep_policy check is off)
            if ((isset(app()->make('laravel-json-api')->getConfig()['deep_policy_check']) and !app()->make('laravel-json-api')->getConfig()['deep_policy_check'])
                and !Authorization::check('viewAny', $className, false)) {
                unset($sortByClass[$className]);
                continue;
            }

            $finalData = array_merge($finalData, $data);

        }

        // Deep policy check
        if (!isset(app()->make('laravel-json-api')->getConfig()['deep_policy_check']) or app()->make('laravel-json-api')->getConfig()['deep_policy_check']) {

            foreach ($finalData as $key => $resource) {
                
                if (!Authorization::check('view', $resource, false)) {
                    self::removeResource($finalData, $key);
                }

            }

        }

        if ($collection instanceof Collection) {
            return $collection = collect($finalData);
        }

        return $collection = $finalData;
    }

    protected static function removeResource(&$collection, $key)
    {
        if ($collection instanceof Collection) {
            $collection->forget($key);
        } else {
            unset($collection[$key]);
        }

        return $collection;
    }
}