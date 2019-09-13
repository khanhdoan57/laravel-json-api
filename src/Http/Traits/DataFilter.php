<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

trait DataFilter {

    /**
    * Remove un-needed data from resource
    *
    * @param array
    * @return array
    */
    private function dataFilter(&$documentData)
    {
        if ($includes = $this->request->query('includes') and is_array($includes)) {

            foreach ($includes as $resourceType => $includeAttributes) {

                $includeAttributes = explode(',', $includeAttributes);
                $includeAttributes = array_filter($includeAttributes);

                $documentData['data'] = $this->includeAttributes($documentData['data'], $resourceType, $includeAttributes);

                if (isset($documentData['included'])) {
                    $documentData['included'] = $this->includeAttributes($documentData['included'], $resourceType, $includeAttributes);
                }

            }

        }

        if ($excludes = $this->request->query('excludes') and is_array($excludes)) {

            foreach ($excludes as $resourceType => $excludeAttributes) {

                $excludeAttributes = explode(',', $excludeAttributes);
                $excludeAttributes = array_filter($excludeAttributes);

                $documentData['data'] = $this->excludeAttributes($documentData['data'], $resourceType, $excludeAttributes);

                if (isset($documentData['included'])) {
                    $documentData['included'] = $this->excludeAttributes($documentData['included'], $resourceType, $excludeAttributes);
                }

            }

        }

        return $documentData;
    }

    private final function includeAttributes($data, $resourceType, $attributes)
    {
        $includeAttributes = function($resource, $resourceType, $attributes) {

            if (!is_array($attributes) or !count($attributes)) {
                return $resource;
            }

            if (!isset($resource['type']) or $resource['type'] !== $resourceType) {
                return $resource;;
            }

            if (!isset($resource['attributes'])) {
                return $resource;;
            }

            foreach ($resource['attributes'] as $key => $value) {
                    
                if (!in_array($key, $attributes)) {
                    unset($resource['attributes'][$key]);
                }

            }

            return $resource;

        };

        if (isset($data['type'])) {
            return $includeAttributes($data, $resourceType, $attributes);
        }

        foreach ($data as $key => $resource) {
            $data[$key] = $includeAttributes($resource, $resourceType, $attributes);
        }

        return $data;
    }

    private final function excludeAttributes(&$data, $resourceType, $attributes)
    {
        $excludeAttributes = function($resource, $resourceType, $attributes) {

            if (!is_array($attributes) or !count($attributes)) {
                return $resource;
            }

            if (!isset($resource['type']) or $resource['type'] !== $resourceType) {
                return $resource;;
            }

            if (!isset($resource['attributes'])) {
                return $resource;;
            }

            foreach ($resource['attributes'] as $key => $value) {
                    
                if (in_array($key, $attributes)) {
                    unset($resource['attributes'][$key]);
                }

            }

            return $resource;

        };

        if (isset($data['type'])) {
            return $excludeAttributes($data, $resourceType, $attributes);
        }

        foreach ($data as $key => $resource) {
            $data[$key] = $excludeAttributes($resource, $resourceType, $attributes);
        }

        return $data;
    }

}