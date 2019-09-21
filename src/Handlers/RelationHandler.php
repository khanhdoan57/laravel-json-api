<?php

namespace HackerBoy\LaravelJsonApi\Handlers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Collection;

class RelationHandler extends Relation {

	/**
	* Loaded relations
	*
	* @var array
	* @access protected
	*/
	protected static $relations = [];

	/**
	* Short cut method to init new RelationHandler object
	*
	* @param \Illuminate\Database\Eloquent\Relations\Relation
	* @return \HackerBoy\LaravelJsonApi\Handlers\RelationHandler
	*/
	public static function handle(Relation $relation)
	{
		$hash = spl_object_hash($relation);

		if (isset(static::$relations[$hash])) {
			return static::$relations[$hash];
		}

		return static::$relations[$hash] = new RelationHandler($relation);
	}

	/**
	* Relation object
	*
	* @var \Illuminate\Database\Eloquent\Relations\Relation
	* @access protected
	*/
	protected $relation;

	/**
	* Count total relationship record
	*
	* @var integer
	* @access protected
	*/
	protected $count = null;

	/**
	* Result limit
	*
	* @var integer
	* @access protected
	*/
	protected $limit = null;

	/**
	* Current offset
	*
	* @var integer
	* @access protected
	*/
	protected $offset = null;

	/**
	* Current relationship page
	*
	* @var integer
	* @access protected
	*/
	protected static $currentRelationshipPage = 1;

	/**
	* Get current relationship page
	*
	* @param void
	* @return integer
	*/
	public static function getCurrentRelationshipPage()
	{
		return static::$currentRelationshipPage;
	}

	/**
	* Set current relationship page
	*
	* @param integer
	* @return void
	*/
	public static function setCurrentRelationshipPage($page)
	{
		$page = intval($page);
		$page = $page > 1 ? $page : 1;
		static::$currentRelationshipPage = $page;
	}

	/**
	* Custom relationship data limit
	*
	* @var integer
	* @access protected
	*/
	protected static $customRelationshipDataLimit = null;

	/**
	* Get current relationship page
	*
	* @param void
	* @return integer
	*/
	public static function getCustomRelationshipDataLimit()
	{
		return static::$currentRelationshipPage;
	}

	/**
	* Set current relationship page
	*
	* @param integer
	* @return void
	*/
	public static function setCustomRelationshipDataLimit($limit)
	{
		$limit = intval($limit);
		$limit = $limit > 1 ? $limit : null;
		static::$customRelationshipDataLimit = $limit;
	}

	/**
	* Flag if this relation is paginated
	*
	* @var bool
	* @access public
	*/
	public $isPaginated = false;

	/**
	* Constructor
	*
	* @param Relation
	* @return void
	*/
	public function __construct(Relation $relation)
	{
		$this->relation = $relation;
	}

	/**
	* Get relation
	*
	* @param void
	* @return Relation $this->relation
	*/
	public function getRelation()
	{
		return $this->relation;
	}

	/**
	* Get count
	*
	* @param void
	* @return integer
	*/
	public function getCount()
	{
		if ($this->count !== null) {
			return $this->count;
		}

		return $this->count = $this->relation->count();
	}

	/**
	* Get limit
	*
	* @param void
	* @return integer
	*/
	public function getLimit()
	{
		if ($this->limit !== null) {
			return $this->limit;
		}

		$this->setLimit(null);

		return $this->limit;
		
	}

	/**
	* Set limit
	*
	* @param integer
	* @return $this
	*/
	public function setLimit($limit)
	{
		if (!$limit and self::$customRelationshipDataLimit) {
			$limit = self::$customRelationshipDataLimit;
		}

		$config = app()->make('laravel-json-api')->getConfig();
		$limit = (is_integer($limit) and $limit >= 1) ? $limit : (isset($config['relationship_result_limit']) ? $config['relationship_result_limit'] : 20);

		if ((isset($config['relationship_maximum_result_limit']) and $limit > $config['relationship_maximum_result_limit']) 
			or (!isset($config['relationship_maximum_result_limit']) and $limit > 100)) {
			$limit = 100;
		}

		$this->limit = $limit;

		return $this;
	}

	/**
	* Get offset
	*
	* @param void
	* @return integer
	*/
	public function getOffset()
	{
		if ($this->offset !== null) {
			return $this->offset;
		}

		$this->setOffset(null);

		return $this->offset;
	}

	/**
	* Set offset
	*
	* @param integer
	* @return $this
	*/
	public function setOffset($offset)
	{
		// Calculate offset from current page
		if ($offset === null) {

			$page = static::$currentRelationshipPage;
			$page = $page >= 1 ? $page : 1;

			$offset = ($page - 1)*$this->getLimit();

		}

		$this->offset = $offset > 0 ? $offset : 0;
		return $this;
	}

	/**
	* Get JSON:API links for relationship
	*
	* @param void
	* @return array
	*/
	public function getJsonApiLinks()
	{	
		// JSONAPI document
		$document = app()->make('laravel-json-api')->getDocument();

		// Models
		$parent = $this->relation->getParent();
		$related = $this->relation->getRelated();

		// Get resources
		$parentResource = $document->getResource($parent);
		$relatedResource = $document->getResource($related);

		// Base url
		$baseUrl = $document->getUrl($parentResource->getType().'/'.$parent->id.'/relationships/'.$relatedResource->getType());

		// Request instance
		$request = \Request::instance();
		$httpQuery = $request->query();

		// Pagination links
		$links = [
			'self' => (function() use ($baseUrl, $httpQuery) {
				$selfQuery = $httpQuery;
				$selfQuery['page'] = static::getCurrentRelationshipPage();
				return $baseUrl.'?'.http_build_query($selfQuery);
			})(),
			'first' => (function() use ($baseUrl, $httpQuery) {
				$firstQuery = $httpQuery;
				$firstQuery['page'] = 1;
				return $baseUrl.'?'.http_build_query($firstQuery);
			})(),
		];

		// Count related resources
		$count = $this->getCount();

		// Prev link
		if (static::$currentRelationshipPage > 1) {
			$prevQuery = $httpQuery;
			$prevQuery['page'] = static::getCurrentRelationshipPage() - 1;
			$links['prev'] = $baseUrl.'?'.http_build_query($prevQuery);
		}

		// Next link
		if ($count > $this->getLimit()*static::$currentRelationshipPage) {
			$nextQuery = $httpQuery;
			$nextQuery['page'] = static::getCurrentRelationshipPage() + 1;
			$links['next'] = $baseUrl.'?'.http_build_query($nextQuery);
		};

		// Last link
		if ($count > $this->getLimit()*static::$currentRelationshipPage) {

			$lastPage = $count / $this->getLimit();
			$lastPageRounded = intval($lastPage);

			if ($lastPage > $lastPageRounded) {
				$lastPage = $lastPageRounded + 1;
			} else {
				$lastPage = $lastPageRounded;
			}

			$lastQuery = $httpQuery;
			$lastQuery['page'] = $lastPage;

			$links['last'] = $baseUrl.'?'.http_build_query($lastQuery);
		}

		return $links;
	}

	/**
	* Paginate relation
	*
	* @param integer $limit
	* @param integer $offset
	* @return \Illuminate\Database\Eloquent\Relations\Relation
	*/
	public function paginate($limit = 0, $offset = 0)
	{
		$this->isPaginated = true;

		// Set limit and offset
		$this->setLimit($limit > 0 ? $limit : null);
		$this->setOffset($offset > 0 ? $offset : null);

		$this->relation->take($this->getLimit())->skip($this->getOffset());

		return $this;
	}

	/**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
    	return $this->relation->addConstraints();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
    	return $this->relation->addEagerConstraints($models);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
    	return $this->relation->initRelation($models, $relation);
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
    	return $this->relation->match($models, $results, $relation);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
    	return $this->relation->getResults();
    }

	/**
	* Handle dynamic method calls to the relationship.
	*
	* @param  string  $method
	* @param  array   $parameters
	* @return mixed
	*/
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->relation, $method], $parameters);
    }
}