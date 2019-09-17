<?php

namespace HackerBoy\LaravelJsonApi;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

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

        // Custom validation rule
        Validator::extend('not_fillable', function ($attribute, $value, $parameters, $validator) {
            return false;
        });

        Validator::replacer('not_fillable', function ($message, $attribute, $rule, $parameters) {
            return 'Attribute '.$attribute.' is not allowed';
        });
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
