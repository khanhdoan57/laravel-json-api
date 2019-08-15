<?php

namespace HackerBoy\LaravelJsonApi\Helpers;

use HackerBoy\LaravelJsonApi\Exceptions\JsonApiException;
use Illuminate\Database\Eloquent\Model;

class Authorization {

	private static $usePolicies = false;
	private static $user = null;

	public static function check($scope, $model, $throwException = true)
	{
		$pass = true;

		if (!self::$usePolicies) {
			return true;
		}

		try {

			if (!self::$user) {
				$pass = false;
			} elseif (!self::$user->can($scope, $model)) {
				$pass = false;
			}
			
		} catch (\Exception $e) {
			$pass = false;
		}

		if (!$pass and $throwException) {

			$className = is_object($model) ? get_class($model) : $model;

			if (is_object($model) and $model instanceof Model) {
				$errorTitle = 'You dont have permission to '.$scope.' resource ['.JsonApi::getResourceTypeByModelClass($className).':'.$model->{$model->getKeyName()}.']';
			} else {
				$errorTitle = 'You dont have permission to '.$scope.' "'.JsonApi::getResourceTypeByModelClass($className).'" resources';
			}

			throw new JsonApiException([
				'errors' => [
					'title' => $errorTitle
				],
				'statusCode' => 403
			]);

		}

		return $pass;
	}

	public static function setConfig($config)
	{
		self::$user = (isset($config['user_resolver']) and is_callable($config['user_resolver'])) ? $config['user_resolver'] : \Auth::user();
		self::$usePolicies = isset($config['use_policies']) ? $config['use_policies'] : false;

		if (@$config['allow_guest_users'] and !self::$user) {

			if (isset($config['guest_user_resolver']) and is_callable($config['guest_user_resolver'])) {
				self::$user = call_user_func($config['guest_user_resolver']);
			} elseif (class_exists('\App\User')) {
				self::$user = new \App\User;
			}

		}
	}

}