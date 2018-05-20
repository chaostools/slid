<?php

if (TEMPLATES) {
	require "core/simplates.php";
}


class Slid {
	private $dir = [];

	private $routes = [];

	public function __construct () {
		header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS, PUT, PATCH');

		$this->dir = $this->getDirectory(ROUTES_DIR);
		$this->routes = $this->getRoutes($this->dir);
	}

	private function getDirectory ($path = '') {
		$result = [];
		$scan = glob($path . '*');

		foreach ($scan as $item) {
			if (is_dir($item))
				$result[basename($item)] = $this->getDirectory($item . '/');
			else
				$result[] = basename($item);
		}

		return $result;
	}

	private function getRoutes ($dir, $prefix = '') {
		if (!is_array($dir)) {
			return false;
		}

		$routes = [];
		foreach ($dir as $key => $value) {
			if (is_numeric($key)) {
				$key = '';
			}

			if ($prefix == "") {
				$newPrefix = $key;
			} else {
				$newPrefix = $prefix . '/' . $key;
			}

			if (is_array($value)) {
				$routes = array_merge($routes, $this->getRoutes($value, $newPrefix));
			} else {
				$path = '/' . $newPrefix . basename($value, ".php");

				if ($value == "index.php") {
					$path = '/' . rtrim($newPrefix, '/') . basename('', ".php");
				}

				$regex = preg_replace('/_\w+/', '(\w+)', $path);
				$regex = '/^' . str_replace('/', '\/', $regex) . '$/';

				$routes[] = [
					'path'  => $path,
					'regex' => $regex,
					'file'  => ROUTES_DIR . $newPrefix . $value,
				];
			}
		}

		return $routes;
	}

	public function run () {
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
					include($route['file']);
				} else {
					// 404 - Not found
					View::error(404);
				}

				if (function_exists(strtolower($method))) {
					if (function_exists('validate')) {
						$valid = call_user_func_array('validate', $matches);

						if (!$valid) {
							View::error(400);
						}
					}

					if (function_exists('init')) {
						call_user_func_array('init');
					}

					call_user_func_array(strtolower($method), $matches);
				} else {
					// 405 - Method not allowed
					View::error(405);
				}

				return;
			}
		}

		// 404 - Not found
		View::error(404);
	}
}


// STATIC:

class DB {
	function __construct () {
	}
}


class View {
	function __construct () {
	}

	public static function error ($code, $message = false) {
		if (!$message) {
			switch ($code) {
				case 100:
					$message = 'Continue';
					break;
				case 101:
					$message = 'Switching Protocols';
					break;
				case 200:
					$message = 'OK';
					break;
				case 201:
					$message = 'Created';
					break;
				case 202:
					$message = 'Accepted';
					break;
				case 203:
					$message = 'Non-Authoritative Information';
					break;
				case 204:
					$message = 'No Content';
					break;
				case 205:
					$message = 'Reset Content';
					break;
				case 206:
					$message = 'Partial Content';
					break;
				case 300:
					$message = 'Multiple Choices';
					break;
				case 301:
					$message = 'Moved Permanently';
					break;
				case 302:
					$message = 'Moved Temporarily';
					break;
				case 303:
					$message = 'See Other';
					break;
				case 304:
					$message = 'Not Modified';
					break;
				case 305:
					$message = 'Use Proxy';
					break;
				case 400:
					$message = 'Bad Request';
					break;
				case 401:
					$message = 'Unauthorized';
					break;
				case 402:
					$message = 'Payment Required';
					break;
				case 403:
					$message = 'Forbidden';
					break;
				case 404:
					$message = 'Not Found';
					break;
				case 405:
					$message = 'Method Not Allowed';
					break;
				case 406:
					$message = 'Not Acceptable';
					break;
				case 407:
					$message = 'Proxy Authentication Required';
					break;
				case 408:
					$message = 'Request Time-out';
					break;
				case 409:
					$message = 'Conflict';
					break;
				case 410:
					$message = 'Gone';
					break;
				case 411:
					$message = 'Length Required';
					break;
				case 412:
					$message = 'Precondition Failed';
					break;
				case 413:
					$message = 'Request Entity Too Large';
					break;
				case 414:
					$message = 'Request-URI Too Large';
					break;
				case 415:
					$message = 'Unsupported Media Type';
					break;
				case 500:
					$message = 'Internal Server Error';
					break;
				case 501:
					$message = 'Not Implemented';
					break;
				case 502:
					$message = 'Bad Gateway';
					break;
				case 503:
					$message = 'Service Unavailable';
					break;
				case 504:
					$message = 'Gateway Time-out';
					break;
				case 505:
					$message = 'HTTP Version not supported';
					break;
				default:
					$message = 'An error occured';
					break;
			}
		}

		http_response_code($code);
		if (file_exists(LAYOUTS_DIR . 'error.php')) {
			include(LAYOUTS_DIR . 'error.php');
		} else {
			include(CORE_DIR . 'layouts/error.php');
		}
	}
}