<?php

namespace HackerBoy\LaravelJsonApi;

use Illuminate\Support\ServiceProvider;

class LaravelJsonApiProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->routes();
    }

	/**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    	$this->app->singleton('laravel-json-api', function($app) {
    		return new LaravelJsonApi(config('laravel_jsonapi'));
    	});
    }

    /**
     * Register routes.
     *
     * @return void
     */
    protected function routes()
    {
       	app()->make('laravel-json-api')->getRouter()->generate();
    }

}
