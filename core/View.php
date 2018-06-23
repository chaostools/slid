<?php
/**
 * Contains the View class
 */


/**
 * Class View
 *
 * Handles outputting of templates, files or other data.
 */
class View {
	/**
	 * Contains the variables to use in the templates
	 *
	 * @var array
	 */
	private static $variables = [];

	/**
	 * Sets a template variable.
	 * Use {$key} in templates to output the variable.
	 *
	 * @param $key   string Template variable key
	 * @param $value mixed  Template variable value
	 *
	 * @return void
	 */
	public static function setVar ($key, $value) {
		self::$variables[$key] = $value;
	}

	/**
	 * Same as View::setVar() but for setting multiple key => value pairs at once.
	 *
	 * @param array $vars The array containing the key and values of the variables
	 *
	 * @return void
	 */
	public static function setVars ($vars) {
		foreach ($vars as $key => $value) {
			self::setVar($key, $value);
		}
	}

	/**
	 * Loads an simplates template located in views/, converts it and then outputs the it.
	 * The converted file is stored with a .php extension next to the .spl.html template. Just ignore it.
	 * See the Simplates class for more info about Simplates templates
	 *
	 * Example:
	 * Template file is located in views/blog/post.spl.html
	 * Layout file is located in layouts/blog.spl.html
	 * => View::simplates('blog/post', 'blog');
	 *
	 * @param string $view   The path to the template in views/. The template must have .spl.html as file extension! Do not include the file extension
	 *                       (.spl.html) here in the parameter!
	 * @param string $layout The layout to use. Uses default.spl.html by default. Set to false to use no layout. The layout file must have .spl.html as
	 *                       file extension! Do not include the file extension (.spl.html) here in the parameter!
	 */
	public static function simplates ($view, $layout = 'default') {
		if ($layout) {
			// Layout converting
			if (file_exists(LAYOUTS_DIR . $layout . '.spl.html')) {
				// Convert the .spl.html file to a .php file
				Simplates::convert(LAYOUTS_DIR . $layout . '.spl.html', LAYOUTS_DIR . $layout . '.php');
			} else {
				// Error
				View::error(500);
				View::console('Layout-File ' . LAYOUTS_DIR . $layout . '.spl.html not found', 'error');
			}
		}

		// View converting
		if (file_exists(VIEWS_DIR . $view . '.spl.html')) {
			// Convert the .spl.html file to a .php file
			Simplates::convert(VIEWS_DIR . $view . '.spl.html', VIEWS_DIR . $view . '.php');
		} else {
			// Error
			View::error(500);
			View::console('View-File ' . VIEWS_DIR . $layout . '.spl.html not found', 'error');
		}

		if (file_exists(VIEWS_DIR . $view . '.php')) {
			// Make template variables public so the view is able to use them
			extract(self::$variables);

			if ($layout && file_exists(LAYOUTS_DIR . $layout . '.php')) {
				include(LAYOUTS_DIR . $layout . '.php');
			} else {
				include(VIEWS_DIR . $view . '.php');
			}
		} else {
			View::error(500);
			View::console('Something went wrong while converting view - File ' . VIEWS_DIR . $view . '.php not found', 'error');
		}
	}

	/**
	 * Serve a file.
	 * This method detects the required mime type (by using View::mime_type()), sets the needed Content-Type header and then just outputs the file.
	 * The file can also be an URL.
	 *
	 * @param string $file The file to serve. For example 'static/images/example.png'
	 * @param bool   $mime If known, the file type. If not specified it automatically detects it.
	 *
	 * @return bool False if file does not exist, or True if successfully outputed.
	 */
	public static function file ($file, $mime = false) {
		if (!file_exists($file) && !preg_match('/^https?:\/\//', $file))
			return false;

		if (!$mime)
			$mime = self::mime_type(pathinfo($file, PATHINFO_EXTENSION));

		header('Content-Type: ' . $mime);
		echo file_get_contents($file);

		return true;
	}

	/**
	 * Wrapper for View::file to output HTML files.
	 * Can be some milliseconds faster because the mime type is already known.
	 *
	 * @param $file The HTML file
	 */
	public static function html ($file) {
		self::file($file, 'text/html');
	}

	/**
	 * Wrapper for View::file to output XML files.
	 * Can be some milliseconds faster because the mime type is already known.
	 *
	 * @param $file The XML file
	 */
	public static function xml ($file) {
		self::file($file, 'text/xml');
	}

	/**
	 * Wrapper for View::file to output CSS files.
	 * Can be some milliseconds faster because the mime type is already known.
	 *
	 * @param $file The CSS file
	 */
	public static function css ($file) {
		self::file($file, 'text/css');
	}

	/**
	 * Wrapper for View::file to output JavaScript files.
	 * Can be some milliseconds faster because the mime type is already known.
	 *
	 * @param $file The JavaScript file
	 */
	public static function js ($file) {
		self::file($file, 'application/javascript');
	}

	/**
	 * Wrapper for View::file to output plain text files.
	 * Can be some milliseconds faster because the mime type is already known.
	 *
	 * @param $file The text file
	 */
	public static function txt ($file) {
		self::file($file, 'text/plain');
	}

	/**
	 * Output JSON data.
	 * This method automatically sets the Content-Type header and outputs the JSON representation of a PHP variable (for example an array).
	 * Useful for JSON APIs.
	 *
	 * @param array $data         The PHP variable to output.
	 * @param int   $json_options Use this to pass options to json_encode. For example JSON_PRETTY_PRINT to output beautified JSON data
	 *
	 * @return void
	 */
	public static function json ($data, $json_options = 0) {
		header('Content-Type: application/json');
		echo json_encode($data, $json_options);
	}

	/**
	 * Outputs an error.
	 * This method outputs the error.php file with the specified error code and message.
	 * If there is no error.php in the views/ directory, the default Nuxt.js error page is used.
	 * This method can be used to output a 404 for example (which is done automatically when the route was not found)
	 *
	 * @param int  $code    The error code. For example 404
	 * @param bool $message The error message. If none specified, the default one for the error code is used.
	 */
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
				case 418:
					$message = 'I\'m a teapot';
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
		if (file_exists(VIEWS_DIR . 'error.php')) {
			include(VIEWS_DIR . 'error.php');
		} else {
			include(CORE_DIR . 'views/error.php');
		}
	}

	/**
	 * Output data to the javascript console.
	 * Skayo loves this function! You have to try it!
	 * You can pass arrays, integers, booleans, strings, ... and this function generates and outputs a <script> tag with the passed data.
	 * Very useful for debugging!
	 *
	 * @param mixed  $data The data to output. For example an array or an integer.
	 * @param string $type The console output type. For example 'log' to use console.log() or 'error' to use console.error(). Defaults to 'log'.
	 */
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

	/**
	 * Just an unmodified output of data. Basically an echo...
	 *
	 * @param string $data The data to output
	 */
	public static function raw ($data) {
		echo $data;
	}

	/**
	 * Get mime type of a file extension.
	 * Used by View::file() to output the correct Content-Type header.
	 *
	 * @param string|bool $ext The extension without a dot, so for example 'png', or False to get an array with all popular file extensions as key and
	 *                         their mime type as value.
	 *
	 * @return array|string|bool The mime type or the array with popular file extensions and their mime type. False if the file extension is unknown.
	 */
	private static function mime_type ($ext = false) {
		$types = [
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
			'zip'     => 'application/zip',
		];

		if (!$ext) return $types;

		$lower_ext = strtolower($ext);

		return isset($types[$lower_ext]) ? $types[$lower_ext] : false;
	}
}