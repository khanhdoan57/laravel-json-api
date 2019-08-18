<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

use HackerBoy\LaravelJsonApi\Exceptions\JsonApiException;

trait CustomQuery {

	/**
	* Query composer
	* 
	* @param array
	* @param object Query object
	* @return void
	*/
	private $conditionCount = 0;
	private function queryComposer($data, $query)
	{
		if (!$data or !is_array($data)) {
			return;
		}

		if (!is_array($data)) {

			throw new JsonApiException([
				'errors' => [
					'title' => 'Invalid query format'
				],
				'statusCode' => 400
			]);

		}

		if ($this->queryDataValidator($data, true)) {
			$this->queryMaker($data, $query);
			return;
		}

		if (isset($data['query'])) {
			$this->queryComposer($data['query'], $query);
			return;
		}

		foreach ($data as $queryData) {
			
			// If this is a valid query data
			if ($this->queryDataValidator($queryData, true)) {
				$this->queryMaker($queryData, $query);
			} else {

				if (!isset($queryData['query']) or !is_array($queryData['query'])) {

					// Throw the exception
					$this->queryDataValidator($queryData);

				}

				$whereMethod = (isset($queryData['boolean']) and strtolower($queryData['boolean']) === 'or') ? 'orWhere' : 'where';

				$query->{$whereMethod}(function($subQuery) use ($queryData) {
					$this->queryComposer($queryData['query'], $subQuery);
				});
			}

		}

		return;
	}

	/**
	* Query maker
	* 
	* @param array
	* @param object Query object
	* @return object Query object
	*/
	private function queryMaker($queryData, $query)
	{
		$queryData = $this->queryDataValidator($queryData);

		// Get maximum number of condition
		$maxConditions = 5;

		if (isset($this->config['resources'][$this->modelClass]['max_query_conditions'])) {
			$maxConditions = intval($this->config['resources'][$this->modelClass]['max_query_conditions']);
			$maxConditions = $maxConditions > 1 ? $maxConditions : 5;
		}

		if ($this->conditionCount >= $maxConditions) {

			throw new JsonApiException([
				'errors' => [
					'title' => 'Too many query conditions. Maximum: '.$maxConditions
				],
				'statusCode' => 400
			]);

		}

		$this->conditionCount++;

		// Check if has custom handler
		$customHandler = isset($this->config['resources'][$this->modelClass]['filter'][$queryData['field']]) ? $this->config['resources'][$this->modelClass]['filter'][$queryData['field']] : null;
		if (is_callable($customHandler)) {
			call_user_func_array($customHandler, [$queryData, $query]);
			return;
		}

		// Auto query builder
		$whereMethod = null;

		if (isset($queryData['boolean']) and $queryData['boolean'] === 'or') {

			if ($queryData['type'] === 'null') {
				$whereMethod = 'orWhereNull';
			} elseif ($queryData['type'] === 'notnull') {
				$whereMethod = 'orWhereNotNull';
			} else {
				$whereMethod = 'orWhere';
			}

		} else {

			if ($queryData['type'] === 'null') {
				$whereMethod = 'whereNull';
			} elseif ($queryData['type'] === 'notnull') {
				$whereMethod = 'whereNotNull';
			} else {
				$whereMethod = 'where';
			}

		}

		// Add the query
		if (in_array($whereMethod, ['orWhereNull', 'orWhereNotNull', 'whereNull', 'whereNotNull'])) {
			$query->{$whereMethod}($queryData['field'], null);
		} else {
			$query->{$whereMethod}($queryData['field'], $queryData['operator'], $queryData['value']);
		}

		return $query;

	}

	private function queryDataValidator($queryData, $return = false)
	{
		if (!is_array($queryData)) {

			if ($return) {
				return false;
			}

			throw new JsonApiException([
				'errors' => [
					'title' => 'Invalid query object'
				],
				'statusCode' => 400
			]);

		}

		// Get list filter fields
		$filter = isset($this->config['resources'][$this->modelClass]['filter']) ? $this->config['resources'][$this->modelClass]['filter'] : [];
		$whitelistedFields = [];

		foreach ($filter as $key => $value) {
				
			if (is_callable($value)) {
				$whitelistedFields[] = $key;
				continue;
			}

			$whitelistedFields[] = $value;

		}

		// Check field in whitelist
		if (!isset($queryData['field'])) {

			if ($return) {
				return false;
			}

			throw new JsonApiException([
				'errors' => [
					'title' => 'Missing field name for query'
				],
				'statusCode' => 400
			]);

		}

		if (!in_array($queryData['field'], $whitelistedFields)) {

			if ($return) {
				return false;
			}
			
			throw new JsonApiException([
				'errors' => [
					'title' => 'Field ('.$queryData['field'].') is not allowed for query'
				],
				'statusCode' => 400
			]);

		}

		// Query type
		if (!isset($queryData['type'])) {
			$queryData['type'] = 'basic';
		}

		$queryData['type'] = strtolower($queryData['type']);

		// Check value
		if (!isset($queryData['value']) and !in_array($queryData['type'], ['null', 'notnull'])) {

			if ($return) {
				return false;
			}
			
			throw new JsonApiException([
				'errors' => [
					'title' => 'Invalid query, missing query value for field ('.$queryData['field'].')'
				],
				'statusCode' => 400
			]);

		}

		// Operator
		if (!isset($queryData['operator'])) {
			$queryData['operator'] = '=';
		}

		// Check operator valid
		if (!in_array($queryData['operator'], ['=', '!=', '>', '<', '>=', '<='])) {

			if ($return) {
				return false;
			}

			throw new JsonApiException([
				'errors' => [
					'title' => 'Operator for field ('.$queryData['field'].') is not allowed'
				],
				'statusCode' => 400
			]);

		}

		return $queryData;
	}

}