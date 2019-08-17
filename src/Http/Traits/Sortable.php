<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

use HackerBoy\LaravelJsonApi\Exceptions\JsonApiException;

trait Sortable {

	/**
	* Ordering
	* 
	* @param object Query builder
	* @return object Query builder
	*/
	public function sortQuery($query)
	{
		if (!isset($this->config['resources'][$this->modelClass]['sortable']) or !is_array($this->config['resources'][$this->modelClass]['sortable'])) {
			return $query;
		}

		// Get sortable
		$sortable = $this->config['resources'][$this->modelClass]['sortable'];

		// Whitelist
		$whitelistedFields = [];

		foreach ($sortable as $key => $value) {
			
			if (is_callable($value)) {
				$whitelistedFields[] = $key;
				continue;
			}

			$whitelistedFields[] = $value;

		}

		if ($sort = $this->request->query('sort')) {

			$sort = explode(',', $sort);
			$sort = array_filter($sort);

			// Check max number of fields
			$maxFields = @intval($this->config['resources'][$this->modelClass]['max_multiple_sorting']);
			$maxFields = $maxFields > 0 ? $maxFields : 2;

			if (count($sort) > $maxFields) {

				throw new JsonApiException([
					'errors' => [
						'title' => 'Too many fields for sorting. Maximum: '.$maxFields
					],
					'statusCode' => 400
				]);
			}

			// Get table name
			$newModelInstance = new $this->modelClass;
			$tableName = $newModelInstance->getTable();
			unset($newModelInstance);

			foreach ($sort as $sortField) {
				
				$desc = false;

				// If having minus
				if (preg_match('/^\-/', $sortField)) {
					$desc = true;
					$sortField = substr($sortField, 1);
				}

				// Not in whitelist
				if (!in_array($sortField, $whitelistedFields)) {
					
					throw new JsonApiException([
						'errors' => [
							'title' => 'Field '.$sortField.' is not allowed for sorting'
						],
						'statusCode' => 400
					]);

				}

				// If having custom handler
				if (isset($sortable[$sortField]) and is_callable($sortable[$sortField])) {
					call_user_func($sortable[$sortField], $query);
					continue;
				}

				// Auto handler
				$query->orderBy($tableName.'.'.$sortField, $desc ? 'desc' : 'asc');
			}

		}

		return $query;
	}

}