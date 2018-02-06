<?php

use Airbrake\Configuration as AirbrakeConfiguration;
use Airbrake\Client as AirbrakeClient;
use Airbrake\Notice as AirbrakeNotice;

App::uses('Router', 'Routing');
App::uses('Debugger', 'Utility');

class AirbrakeError extends ErrorHandler
{

	/**
	 * Creates a new Airbrake instance, or returns an instance created earlier.
	 * You can pass options to Airbrake\Configuration by setting the AirbrakeCake.options
	 * configuration property.
	 *
	 * For example to set the environment name:
	 *
	 * ```
	 * Configure::write('AirbrakeCake.options', array(
	 * 	'environmentName' => 'staging'
	 * ));
	 * ```
	 *
	 * @return Airbrake\Client
	 */
	public static function getAirbrake() {
		static $client = null;

		if ($client === null) {
			$apiKey = Configure::read('AirbrakeCake.apiKey');
			$options = Configure::read('AirbrakeCake.options');

			if (!$options) {
				$options = array();
			}

			if (php_sapi_name() !== 'cli') {
				$request = Router::getRequest();

				if ($request) {
					$options['component'] = $request->params['controller'];
					$options['action'] = $request->params['action'];
				}

				if (!empty($_SESSION)) {
					$options['extraParameters'] = Hash::get($options, 'extraParameters', array());
					$options['extraParameters']['User']['id'] = Hash::get($_SESSION, 'Auth.User.id');
				}
			}

			$config = new AirbrakeConfiguration($apiKey, $options);
			$client = new AirbrakeClient($config);
		}

		return $client;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function handleError($code, $description, $file = null, $line = null, $context = null) {
		list($error, $log) = self::mapErrorCode($code);

		$backtrace = debug_backtrace();
		if (count($backtrace) > 1) {
			array_shift($backtrace);
		}

		$trace = Debugger::trace(array('format' => 'points'));

		$notice = new AirbrakeNotice();
		$notice->load(array(
			'errorClass' => $error,
			'backtrace' => $backtrace,
			'errorMessage' => $description,
			'extraParameters' => array('CakeTrace' => $trace),
		));

		$client = static::getAirbrake();
		$client->notify($notice);

		return parent::handleError($code, $description, $file, $line, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function handleException($exception) {
		$client = static::getAirbrake();
		$client->notifyOnException($exception);

		return parent::handleException($exception);
	}
}
