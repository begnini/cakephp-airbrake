<?php

use Airbrake\Notifier as AirbrakeNotifier;
use Airbrake\ErrorHandler as AirbrakeErrorHandler;
use Airbrake\Instance as AirbrakeInstance;

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
			$projectId = Configure::read('AirbrakeCake.projectId');
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

			$notifier = new AirbrakeNotifier([
				'projectId' => $projectId,
				'projectKey' => $apiKey,				
			]);

			AirbrakeInstance::set($notifier);

			$client = new AirbrakeErrorHandler($notifier);
		}

		return $client;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function handleError($code, $description, $file = null, $line = null, $context = null) {
		$client = static::getAirbrake();

		$client->onError($code, $description, $file, $line);
		return parent::handleError($code, $description, $file, $line, $context);		
	}

	/**
	 * {@inheritDoc}
	 */
	public static function handleException($exception) {
		$client = static::getAirbrake();

		$client->onException($exception);
        parent::handleException($exception);
	}


	public static function handleFatalError($code, $description, $file, $line) {
		$client = static::getAirbrake();

		$client->onError($code, $description, $file, $line);
		return parent::handleFatalError($code, $description, $file, $line);
	}
}
