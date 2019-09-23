<?php

namespace HackerBoy\LaravelJsonApi\Handlers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Support\Arr;
use Closure;

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
		$limit = $limit >= 1 ? $limit : null;
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
		return $this->count;
	}

	/**
	* Set count
	*
	* @param void
	* @return $this
	*/
	public function setCount($count)
	{
		$this->count = $count;
		return $this;
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
		$httpQuery = [];

		// Check if using custom limit
		$defaultLimit = @app()->make('laravel-json-api')->getConfig()['relationship_result_limit'];
		$defaultLimit = $defaultLimit ?: 20;

		if (intval($this->getLimit()) !== @$defaultLimit) {
			$httpQuery['limit'] = $this->getLimit();
		}

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

		// Next link - has count or blind increase 1
		if (($count > $this->getLimit()*static::$currentRelationshipPage) or $count === null) {
			$nextQuery = $httpQuery;
			$nextQuery['page'] = static::getCurrentRelationshipPage() + 1;
			$links['next'] = $baseUrl.'?'.http_build_query($nextQuery);
		};

		// Last link
		if ($count !== null) {

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
	* @return \Illuminate\Database\Eloquent\Relations\Relation
	*/
	public function paginate($limit = 0)
	{
		$this->isPaginated = true;

		// Counting
		$this->getCount();

		// Set limit and offset
		if (!static::$customRelationshipDataLimit) {
			$this->setLimit($limit > 0 ? $limit : null);
		}
			
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

    /**
    * {@inheritdoc}
    */
    protected function forwardCallTo($object, $method, $parameters)
    {
    	return $this->getRelation($object, $method, $parameters);
    }

	/**
	* Get the relationship for eager loading.
	*
	* @return \Illuminate\Database\Eloquent\Collection
	*/
    public function getEager()
    {
        return $this->getRelation()->get();
    }

	/**
	* Execute the query as a "select" statement.
	*
	* @param  array  $columns
	* @return \Illuminate\Database\Eloquent\Collection
	*/
    public function get($columns = ['*'])
    {
        return $this->getRelation()->get($columns);
    }

    /**
	* Touch all of the related models for the relationship.
	*
	* @return void
	*/
    public function touch()
    {
        $this->getRelation()->touch();
    }

    /**
     * Run a raw update against the base query.
     *
     * @param  array  $attributes
     * @return int
     */
    public function rawUpdate(array $attributes = [])
    {
        return $this->getRelation()->rawUpdate($attributes);
    }

    /**
	* {@inheritdoc}
	*/
    public function getRelationExistenceCountQuery(Builder $query, Builder $parentQuery)
    {
        return $this->getRelation()->getRelationExistenceCountQuery($query, $parentQuery);
    }

    /**
	* {@inheritdoc}
	*/
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $this->getRelation()->getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
    * {@inheritdoc}
    */
    protected function getKeys(array $models, $key = null)
    {
        return $this->getRelation()->getKeys($models, $key);
    }

	/**
	* {@inheritdoc}
	*/
    public function getQuery()
    {
        return $this->getRelation()->getQuery();
    }

	/**
	* Get the base query builder driving the Eloquent builder.
	*
	* @return \Illuminate\Database\Query\Builder
	*/
    public function getBaseQuery()
    {
        return $this->getRelation()->getBaseQuery();
    }

	/**
	* Get the parent model of the relation.
	*
	* @return \Illuminate\Database\Eloquent\Model
	*/
    public function getParent()
    {
        return $this->getRelation()->getParent();
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->getRelation()->getQualifiedParentKeyName();
    }

    /**
     * Get the related model of the relation.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getRelated()
    {
        return $this->getRelation()->getRelated();
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function createdAt()
    {
        return $this->getRelation()->createdAt();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updatedAt()
    {
        return $this->getRelation()->updatedAt();
    }

    /**
     * Get the name of the related model's "updated at" column.
     *
     * @return string
     */
    public function relatedUpdatedAt()
    {
        return $this->getRelation()->relatedUpdatedAt();
    }

    /**
     * {@inheritdoc}
     */
    protected function whereInMethod(Model $model, $key)
    {
        return $this->getRelation()->whereInMethod($model, $key);
    }
}