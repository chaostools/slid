<?php
/**
 * This file includes all the core files and contains the Slid class.
 * Include this file to get access to all Slid features and functions. (Only used by index.php)
 */

if (TEMPLATES) {
	require CORE_DIR . 'Simplates.php';
}

if (DATABASE) {
	require CORE_DIR . 'DB.php';
}

if (LANG) {
	require CORE_DIR . 'Lang.php';
}

require CORE_DIR . 'View.php';


/**
 * Class Slid
 *
 * This class is only used by the index.php file and handles routing and error outputting
 */
class Slid {
	/**
	 * Contains multidimensional array of the files in the routes/ directory
	 *
	 * @var array
	 */
	private $dir = [];

	/**
	 * Contains the generated routes
	 *
	 * @var array
	 */
	private $routes = [];

	/**
	 * Slid constructor.
	 * Defines an error handler and calls the route-generating functions
	 *
	 * @return void
	 */
	public function __construct () {
		set_error_handler('Slid::error');

		$this->dir = $this->getRoutesTree(ROUTES_DIR);
		$this->generateRoutes($this->dir);
	}

	/**
	 * Goes through all the found files in the given path and returns them as a multidimensional array
	 *
	 * @param string $path Path to scan
	 *
	 * @return array A multidimensional array with all the directories and files
	 */
	private function getRoutesTree ($path = '') {
		$result = [];
		$scan = glob($path . '*');

		foreach ($scan as $item) {
			if (is_dir($item))
				$result[basename($item)] = $this->getRoutesTree($item . '/');
			else
				$result[] = basename($item);
		}

		return $result;
	}

	/**
	 * Add a route to the routes array
	 *
	 * @param string $path  The route path (actually not used)
	 * @param string $regex The route path formatted as a regex to match the url
	 * @param string $file  The path to the file of the route
	 *
	 * @return void
	 */
	private function addRoute ($path, $regex, $file) {
		$this->routes[] = [
			'path'  => $path,
			'regex' => $regex,
			'file'  => $file,
		];
	}

	/**
	 * Generate routes from the multidimensional array, returned from getRoutesTree
	 *
	 * @param array  $dir    The multidimensional file scan
	 * @param string $prefix Don't mind this parameter, it's just needed for recursion
	 *
	 * @return array|bool Returns the generated routes or just false if $dir isn't an array
	 */
	private function generateRoutes ($dir, $prefix = '') {
		if (!is_array($dir)) {
			return false;
		}

		$routes = [];
		foreach ($dir as $dirname => $item) {
			if (is_numeric($dirname)) {
				$dirname = '';
			}

			if ($prefix === '') {
				$newPrefix = $dirname;
			} else {
				$newPrefix = $prefix . '/' . $dirname;
			}

			if (is_array($item)) {
				$routes = array_merge($routes, $this->generateRoutes($item, $newPrefix));
			} else {
				$path = '/' . $newPrefix . basename($item, ".php");

				if ($item === 'index.php')
					$path = '/' . rtrim($newPrefix, '/') . basename('', '.php');

				$regex = preg_replace('/\/_\w+/', '/([\w-]+)', $path);
				$regex = '/^' . str_replace('/', '\/', $regex) . '$/';

				$file = ROUTES_DIR . $newPrefix . $item;

				$this->addRoute($path, $regex, $file);
			}
		}

		return $routes;
	}

	/**
	 * Starts the router.
	 * Matches the generated routes with the request url and then executes the route-file and it's functions (get,
	 * post, init, validate, ...)
	 *
	 * @return void
	 *
	 * @todo Global route file that is called before each normal route file
	 */
	public function runRouting () {
		$request_path = strtok($_SERVER['REQUEST_URI'], '?');
		$method = $_SERVER['REQUEST_METHOD'];

		if ($request_path != '/')
			$request_path = rtrim($request_path, '/');

		if (preg_match('/^\/*$/', $request_path))
			$request_path = '/';

		foreach ($this->routes as $route) {
			if (preg_match($route['regex'], $request_path, $matches)) {
				unset($matches[0]);

				if (file_exists($route['file'])) {
					include $route['file'];
				} else {
					// 404 - Not found
					View::error(404);
					View::console('File ' . $route['file'] . ' not found', 'error');
				}

				if (function_exists($method) || function_exists('request')) {
					if (function_exists('validate')) {
						$valid = call_user_func_array('validate', $matches);

						if (!$valid) {
							View::error(400);
							View::console('Invalid request', 'error');
						}
					}

					if (function_exists('init')) {
						call_user_func('init');
					}

					if (function_exists($method)) {
						call_user_func_array($method, $matches);
					} else if (function_exists('request')) {
						array_unshift($matches, $method);
						call_user_func_array('request', $matches);
					}

					if (function_exists('finalize')) {
						call_user_func('finalize');
					}
				} else {
					// 405 - Method not allowed
					View::error(405);
					View::console('Method ' . $method . ' is not allowed', error);
				}

				return;
			}
		}

		// 404 - Not found
		View::error(404);
		View::console('Page ' . $request_path . ' not found', error);
	}


	/**
	 * Callback for set_error_handler to display errors in the javascript console.
	 * Don't call this function directly. Use trigger_error().
	 *
	 * @param int    $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int    $errline
	 *
	 * @return bool Always returns true
	 */
	public static function error ($errno, $errstr, $errfile, $errline) {
		switch ($errno) {
			case E_USER_ERROR:
			case E_ERROR:
				$error_level = 'Error';
				$console_function = 'error';
				break;

			case E_USER_WARNING:
			case E_WARNING:
				$error_level = 'Warning';
				$console_function = 'warn';
				break;

			case E_USER_NOTICE:
			case E_NOTICE:
				$error_level = 'Notice';
				$console_function = 'info';
				break;

			default:
				$error_level = 'Info';
				$console_function = 'log';
				break;
		}

		$errfile = str_replace('\\', '\/', $errfile);

		$error_message = "$error_level: $errstr in $errfile on line $errline";

		View::console($error_message, $console_function);

		return true;
	}
}
