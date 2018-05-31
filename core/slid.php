<?php

if (TEMPLATES) {
	require "core/simplates.php";
}


class Slid {
	private $dir = [];

	private $routes = [];

	public function __construct () {
		set_error_handler('Slid::error');

		$this->dir = $this->getRoutesTree(ROUTES_DIR);
		$this->generateRoutes($this->dir);
	}

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

	private function addRoute ($path, $regex, $file) {
		$this->routes[] = [
			'path'  => $path,
			'regex' => $regex,
			'file'  => $file,
		];
	}

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

				$regex = preg_replace('/_\w+/', '(\w+)', $path);
				$regex = '/^' . str_replace('/', '\/', $regex) . '$/';

				$file = ROUTES_DIR . $newPrefix . $item;

				$this->addRoute($path, $regex, $file);
			}
		}

		return $routes;
	}

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
					View::console('File ' . $route['file'] . ' not found', error);
				}

				if (function_exists($method) || function_exists('request')) {
					if (function_exists('validate')) {
						$valid = call_user_func_array('validate', $matches);

						if (!$valid) {
							View::error(400);
							View::console('Invalid request', error);
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


	// Callback for set_error_handler
	public static function error($errno, $errstr, $errfile, $errline) {
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


// STATIC:

class DB {
	public static $db;

	private static $queries = [];

	private static $groups = [];

	public static function connect () {
		if (self::$db) return; // Do not connect if already connected

		self::$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

		if (self::$db->connect_error) {
			View::error(500, 'Database Connection Error');
			View::console('Error (' . self::$db->connect_errno . '): ' . self::$db->connect_error, 'error');
		}
	}

	public static function close () {
		if (!self::$db) return; // Do not close if not connected

		self::$db->close();
	}

	// Convert Multidimensional to Single-Dimensional Array
	private static function flattenArray ($arr, $prefix = "") {
		if (!is_array($arr)) {
			return false;
		}

		$result = [];

		foreach ($arr as $key => $value) {
			if ($prefix === '') {
				$newPrefix = $key;
			} else {
				$newPrefix = $prefix . '.' . $key;
			}
			if (is_array($value)) {
				$result = array_merge($result, self::flattenArray($value, $newPrefix));
			} else {
				$result[$newPrefix] = $value;
			}
		}

		return $result;
	}

	// Read JSON file containing queries
	public static function getQueries ($queryFile) {
		if(!isset(self::$queries[$queryFile])) {
			$rawQueries = json_decode(file_get_contents(QUERIES_DIR . $queryFile . '.json'), true);
			$queries = self::flattenArray($rawQueries);

			self::$queries[$queryFile] = $queries;
		}

		return self::$queries[$queryFile];
	}

	private static function getQuery ($queryName) {
		$queryFile = explode('.', $queryName)[0];
		$queryName = substr($queryName, strlen($queryFile . '.'));

		$queries = self::getQueries($queryFile);

		return $queries[$queryName];
	}

	// Perform Query with parameters
	public static function query ($queryName, $vars = [], $group = '') {
		self::connect();

		$query = self::getQuery($queryName);

		preg_match_all('/\{(\w+)\}/', $query, $matches);

		foreach ($matches[1] as $match) {
			if (!isset($vars[$match])) {
				continue;
			}

			$parameter = self::$db->real_escape_string($vars[$match]);
			$query = str_replace('{' . $match . '}', $parameter, $query);
		}

		if ($group !== '') {
			if (!isset(self::$groups[$group]))
				self::$groups[$group] = [];

			return self::$groups[$group][] = $query;
		}

		return self::rawQuery($query);
	}

	public static function executeGroup($groupName) {
		foreach (self::$groups[$groupName] as $query) {
			self::rawQuery($query);
		}
	}

	// Perform a raw query
	public static function rawQuery ($query) {
		self::connect();

		return self::$db->query($query);
	}

	// Return errors
	public static function error () {
		return self::$db->error;
	}

	// Return last insert ID
	public static function insert_id () {
		return self::$db->insert_id;
	}
}


class View {
	private static $variables = [];

	function __construct () {
	}

	public static function setVar($key, $value) {
		self::$variables[$key] = $value;
	}

	public static function setVars($vars) {
		foreach ($vars as $key => $value) {
			self::setVar($key, $value);
		}
	}

	// Load spl files located in view/ and convert them, then execute them
	public static function simplates($filePath) {
		if(file_exists(VIEWS_DIR . $filePath . '.spl.html')) {
			// Convert the .spl.html file to a .php file
			Simplates::convert(VIEWS_DIR . $filePath . '.spl.html', VIEWS_DIR . $filePath . '.php');
		}

		if(file_exists(VIEWS_DIR . $filePath . '.php')) {
			extract(self::$variables);
			include(VIEWS_DIR . $filePath . '.php');
		} else {
			View::error(500);
			View::console('File ' . VIEWS_DIR . $filePath . '.php not found', 'error');
		}
	}

	public static function file($file, $mime = false) {
		if(file_exists($file)) {
			if(!$mime) {
				$mime = self::mime_type(pathinfo($file, PATHINFO_EXTENSION));
			}

			header('Content-Type: ' . $mime);
			echo file_get_contents($file);
		}
	}

	public static function html($file) {
		self::file($file, 'text/html');
	}

	public static function xml($file) {
		self::file($file, 'text/xml');
	}

	public static function css($file) {
		self::file($file, 'text/css');
	}

	public static function js($file) {
		self::file($file, 'application/javascript');
	}

	public static function txt($file) {
		self::file($file, 'text/plain');
	}

	public static function json($data, $json_option) {
		header('Content-Type: application/json');
		echo json_encode($data, $json_option);
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

	public static function console ($data, $type = 'log') {
		if (is_array($data) || is_object($data)) {
			$arrayName = uniqid('array');
			echo "<script>var $arrayName = " . json_encode($data, JSON_PRETTY_PRINT) . "; console.$type($arrayName);</script>";
		} else {
			switch (gettype($data)) {
				case 'string':
					$data = '`' . $data . '`';
					break;

				case 'NULL':
					$data = null;
					break;

				case 'boolean':
					$data = ($data) ? 'true' : 'false';
					break;
			}

			echo "<script>console.$type($data);</script>";
		}
	}

	// Unmodified output of data
	public static function raw ($data) {
		echo $data;
	}

	private static function mime_type($ext = null)
	{
		$types = array(
			'ai'      => 'application/postscript',
			'aif'     => 'audio/x-aiff',
			'aifc'    => 'audio/x-aiff',
			'aiff'    => 'audio/x-aiff',
			'asc'     => 'text/plain',
			'atom'    => 'application/atom+xml',
			'atom'    => 'application/atom+xml',
			'au'      => 'audio/basic',
			'avi'     => 'video/x-msvideo',
			'bcpio'   => 'application/x-bcpio',
			'bin'     => 'application/octet-stream',
			'bmp'     => 'image/bmp',
			'cdf'     => 'application/x-netcdf',
			'cgm'     => 'image/cgm',
			'class'   => 'application/octet-stream',
			'cpio'    => 'application/x-cpio',
			'cpt'     => 'application/mac-compactpro',
			'csh'     => 'application/x-csh',
			'css'     => 'text/css',
			'csv'     => 'text/csv',
			'dcr'     => 'application/x-director',
			'dir'     => 'application/x-director',
			'djv'     => 'image/vnd.djvu',
			'djvu'    => 'image/vnd.djvu',
			'dll'     => 'application/octet-stream',
			'dmg'     => 'application/octet-stream',
			'dms'     => 'application/octet-stream',
			'doc'     => 'application/msword',
			'dtd'     => 'application/xml-dtd',
			'dvi'     => 'application/x-dvi',
			'dxr'     => 'application/x-director',
			'eps'     => 'application/postscript',
			'etx'     => 'text/x-setext',
			'exe'     => 'application/octet-stream',
			'ez'      => 'application/andrew-inset',
			'gif'     => 'image/gif',
			'gram'    => 'application/srgs',
			'grxml'   => 'application/srgs+xml',
			'gtar'    => 'application/x-gtar',
			'hdf'     => 'application/x-hdf',
			'hqx'     => 'application/mac-binhex40',
			'htm'     => 'text/html',
			'html'    => 'text/html',
			'ice'     => 'x-conference/x-cooltalk',
			'ico'     => 'image/x-icon',
			'ics'     => 'text/calendar',
			'ief'     => 'image/ief',
			'ifb'     => 'text/calendar',
			'iges'    => 'model/iges',
			'igs'     => 'model/iges',
			'jpe'     => 'image/jpeg',
			'jpeg'    => 'image/jpeg',
			'jpg'     => 'image/jpeg',
			'js'      => 'application/x-javascript',
			'json'    => 'application/json',
			'kar'     => 'audio/midi',
			'latex'   => 'application/x-latex',
			'lha'     => 'application/octet-stream',
			'lzh'     => 'application/octet-stream',
			'm3u'     => 'audio/x-mpegurl',
			'man'     => 'application/x-troff-man',
			'mathml'  => 'application/mathml+xml',
			'me'      => 'application/x-troff-me',
			'mesh'    => 'model/mesh',
			'mid'     => 'audio/midi',
			'midi'    => 'audio/midi',
			'mif'     => 'application/vnd.mif',
			'mov'     => 'video/quicktime',
			'movie'   => 'video/x-sgi-movie',
			'mp2'     => 'audio/mpeg',
			'mp3'     => 'audio/mpeg',
			'mpe'     => 'video/mpeg',
			'mpeg'    => 'video/mpeg',
			'mpg'     => 'video/mpeg',
			'mpga'    => 'audio/mpeg',
			'ms'      => 'application/x-troff-ms',
			'msh'     => 'model/mesh',
			'mxu'     => 'video/vnd.mpegurl',
			'nc'      => 'application/x-netcdf',
			'oda'     => 'application/oda',
			'ogg'     => 'application/ogg',
			'pbm'     => 'image/x-portable-bitmap',
			'pdb'     => 'chemical/x-pdb',
			'pdf'     => 'application/pdf',
			'pgm'     => 'image/x-portable-graymap',
			'pgn'     => 'application/x-chess-pgn',
			'png'     => 'image/png',
			'pnm'     => 'image/x-portable-anymap',
			'ppm'     => 'image/x-portable-pixmap',
			'ppt'     => 'application/vnd.ms-powerpoint',
			'ps'      => 'application/postscript',
			'qt'      => 'video/quicktime',
			'ra'      => 'audio/x-pn-realaudio',
			'ram'     => 'audio/x-pn-realaudio',
			'ras'     => 'image/x-cmu-raster',
			'rdf'     => 'application/rdf+xml',
			'rgb'     => 'image/x-rgb',
			'rm'      => 'application/vnd.rn-realmedia',
			'roff'    => 'application/x-troff',
			'rss'     => 'application/rss+xml',
			'rtf'     => 'text/rtf',
			'rtx'     => 'text/richtext',
			'sgm'     => 'text/sgml',
			'sgml'    => 'text/sgml',
			'sh'      => 'application/x-sh',
			'shar'    => 'application/x-shar',
			'silo'    => 'model/mesh',
			'sit'     => 'application/x-stuffit',
			'skd'     => 'application/x-koan',
			'skm'     => 'application/x-koan',
			'skp'     => 'application/x-koan',
			'skt'     => 'application/x-koan',
			'smi'     => 'application/smil',
			'smil'    => 'application/smil',
			'snd'     => 'audio/basic',
			'so'      => 'application/octet-stream',
			'spl'     => 'application/x-futuresplash',
			'src'     => 'application/x-wais-source',
			'sv4cpio' => 'application/x-sv4cpio',
			'sv4crc'  => 'application/x-sv4crc',
			'svg'     => 'image/svg+xml',
			'svgz'    => 'image/svg+xml',
			'swf'     => 'application/x-shockwave-flash',
			't'       => 'application/x-troff',
			'tar'     => 'application/x-tar',
			'tcl'     => 'application/x-tcl',
			'tex'     => 'application/x-tex',
			'texi'    => 'application/x-texinfo',
			'texinfo' => 'application/x-texinfo',
			'tif'     => 'image/tiff',
			'tiff'    => 'image/tiff',
			'tr'      => 'application/x-troff',
			'tsv'     => 'text/tab-separated-values',
			'txt'     => 'text/plain',
			'ustar'   => 'application/x-ustar',
			'vcd'     => 'application/x-cdlink',
			'vrml'    => 'model/vrml',
			'vxml'    => 'application/voicexml+xml',
			'wav'     => 'audio/x-wav',
			'wbmp'    => 'image/vnd.wap.wbmp',
			'wbxml'   => 'application/vnd.wap.wbxml',
			'wml'     => 'text/vnd.wap.wml',
			'wmlc'    => 'application/vnd.wap.wmlc',
			'wmls'    => 'text/vnd.wap.wmlscript',
			'wmlsc'   => 'application/vnd.wap.wmlscriptc',
			'wrl'     => 'model/vrml',
			'xbm'     => 'image/x-xbitmap',
			'xht'     => 'application/xhtml+xml',
			'xhtml'   => 'application/xhtml+xml',
			'xls'     => 'application/vnd.ms-excel',
			'xml'     => 'application/xml',
			'xpm'     => 'image/x-xpixmap',
			'xsl'     => 'application/xml',
			'xslt'    => 'application/xslt+xml',
			'xul'     => 'application/vnd.mozilla.xul+xml',
			'xwd'     => 'image/x-xwindowdump',
			'xyz'     => 'chemical/x-xyz',
			'zip'     => 'application/zip'
		);

		if (is_null($ext)) return $types;

		$lower_ext = strtolower($ext);

		return isset($types[$lower_ext]) ? $types[$lower_ext] : null;
	}
}