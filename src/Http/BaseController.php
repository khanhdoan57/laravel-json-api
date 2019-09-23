<?php

namespace HackerBoy\LaravelJsonApi\Http;

// Is lumen
if (class_exists('Laravel\Lumen\Application')) {

	class BaseController extends \Laravel\Lumen\Routing\Controller {}

} else { // Is laravel

	class BaseController extends \Illuminate\Routing\Controller  {}

}