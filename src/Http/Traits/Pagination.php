<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

trait Pagination {

	protected function requestPagination()
	{
		$request = $this->request;

		$resultLimit = @intval($this->config['result_limit']);
		$resultLimit = $resultLimit > 0 ? $resultLimit : 20;

		$maximumResultLimit = @intval($this->config['maximum_result_limit']);
		$maximumResultLimit = $maximumResultLimit > 0 ? $maximumResultLimit : 100;

		$page = intval($request->query('page'));
        $page = ($page > 1) ? $page : 1;
        $limit = intval($request->query('limit'));
        $limit = ($limit >= 1 and $limit <= $maximumResultLimit) ? $limit : $resultLimit;

        return [$page, $limit, ($page-1)*$limit];
	}

	protected function responsePagination($query)
	{
		list($page, $limit, $offset) = $this->requestPagination();

		// Count
		$count = $query->count();

		// Prefix
		$prefix = @$this->config['prefix'];

		// Http query
		$httpQuery = $this->request->query();

		// Get resource type
		$newModel = new $this->modelClass;
		$resourceInstance = $this->document->getResourceInstance($newModel);
		$resourceType = $resourceInstance->getType($newModel);
		unset($newModel);

		$baseLink = $this->document->getUrl($resourceType);

		// First link
		$firstLinkQuery = $httpQuery;
		$firstLinkQuery['page'] = 1;
		$firstLink = $baseLink.'?'.http_build_query($firstLinkQuery);

		// Last link
		$calculatePage = $limit > 0 ? ($count / $limit) : 0;
		$calculatePage = $calculatePage > 1 ? $calculatePage : 1;
		$calculatePageRounded = intval($calculatePage);
		$calculatePage = ($calculatePage > $calculatePageRounded) ? $calculatePage+1 : $calculatePage;
		$lastLinkQuery = $httpQuery;
		$lastLinkQuery['page'] = $calculatePage;
		$lastLink = $baseLink.'?'.http_build_query($lastLinkQuery);

		// Self link
		$selfLink = $baseLink.(count($httpQuery) ? '?'.http_build_query($httpQuery) : '');

		// Create pagination
		$pagination = [
			'self' => $selfLink,
	        'first' => $firstLink,
	        'last' => $lastLink,
	    ];

	    // Prev link
	    if ($page > 1) {
	    	$prevLinkQuery = $httpQuery;
	    	$prevLinkQuery['page']--;
	    	$prevLink = $baseLink.'?'.http_build_query($prevLinkQuery);
	    	$pagination['prev'] = $prevLink;
	    }

	    // Next link
	    if ($page < $calculatePage) {
	    	
	    	$nextLinkQuery = $httpQuery;

	    	if (!isset($nextLinkQuery['page'])) {
	    		$nextLinkQuery['page'] = 1;
	    	}

	    	$nextLinkQuery['page']++;
	    	$nextLink = $baseLink.'?'.http_build_query($nextLinkQuery);
	    	$pagination['next'] = $nextLink;
	    }

		$pagination = $this->document->makePagination($pagination);

		$this->document->setLinks($pagination);
	}
}