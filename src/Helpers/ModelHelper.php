<?php

namespace HackerBoy\LaravelJsonApi\Helpers;

use Illuminate\Database\Eloquent\Model;

class ModelHelper {

    public static function getAttributeValues($collection, $key = 'id')
    {
        $attributes = [];

        foreach ($collection as $o) {
            $attributes[] = $o->{$key};
        }

        return array_unique(array_filter($attributes));
    }

    public static function removeUnauthorizedResources(array &$data)
    {
    	// Sort by class
    	$sortByClass = [];

    	$finalData = [];

    	foreach ($data as $modelObject) {
    		
    		$className = get_class($modelObject);

    		if (!isset($sortByClass[$className])) {
    			$sortByClass[$className] = [];
    		}

    		$sortByClass[$className][] = $modelObject;

    	}

    	foreach ($sortByClass as $className => $data) {
	
			// Done have permission    		
    		if (!Authorization::check('viewAny', $className, false)) {
    			unset($sortByClass[$className]);
    			continue;
    		}

    		$finalData = array_merge($finalData, $data);

    	}

    	$data = $finalData;
    }

}