<?php

namespace CustomTable;

class Hook
{
	public static function init()
	{
		self::addAction();
	}

	public static function addAction()
	{
		add_action('rest_api_init', [__CLASS__, 'restApiInit'], 9);
	}

	public static function restApiInit()
	{
		global $registeredTables;

		if (!is_array($registeredTables)) {
			return;
		}

		/**
		 * @var Table $table
		 */
		foreach ($registeredTables as $table) {

			// Skip tables that are not setup to being shown in rest
			if (!$table->getShowInRest()) {
				continue;
			}

			$class = !empty($table->getRestControllerClass()) ? $table->getRestControllerClass() : RestController::class;

			// Skip if rest controller class doesn't exists
			if (!class_exists($class)) {
				continue;
			}

			/**
			 * @var RestController $controller
			 */
			$controller = new $class($table->getName());

			// Check if controller is subclass of WP_REST_Controller to check if should call to the register_routes() function
			if (!is_subclass_of($controller, 'WP_REST_Controller')) {
				continue;
			}

			$controller->register_routes();

		}

		// Trigger CT rest API init hook
		do_action('custom_table_rest_api_init');
	}
}