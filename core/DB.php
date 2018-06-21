<?php


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
	public static function getQueriesFromFile ($queryFile) {
		if (!isset(self::$queries[$queryFile])) {
			$rawQueries = json_decode(file_get_contents(QUERIES_DIR . $queryFile . '.json'), true);
			$queries = self::flattenArray($rawQueries);

			self::$queries[$queryFile] = $queries;
		}

		return self::$queries[$queryFile];
	}

	private static function getQuery ($queryName) {
		$queryFile = explode('.', $queryName)[0];
		$queryName = substr($queryName, strlen($queryFile . '.'));

		$queries = self::getQueriesFromFile($queryFile);

		return $queries[$queryName];
	}

	// Perform Query with parameters
	public static function query ($queryName, $vars = [], $group = '') {
		self::connect();

		$query = self::getQuery($queryName);

		preg_match_all('/\{(\w+)\}/', $query, $varReplacements);

		foreach ($varReplacements[1] as $varName) {
			if (!isset($vars[$varName])) {
				continue;
			}

			$parameter = self::$db->real_escape_string($vars[$varName]);
			$query = str_replace('{' . $varName . '}', $parameter, $query);
		}

		if ($group !== '') {
			if (!isset(self::$groups[$group]))
				self::$groups[$group] = [];

			return self::$groups[$group][] = $query;
		}

		return self::rawQuery($query);
	}

	public static function executeGroup ($groupName) {
		foreach (self::$groups[$groupName] as $query) {
			self::rawQuery($query);
		}
	}

	// Perform a raw query
	public static function rawQuery ($query) {
		self::connect();

		return self::$db->query($query);
	}

	public static function getRows ($result) {
		$rows = [];

		if ($result->num_rows > 0) {
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	// Return errors
	public static function getError () {
		return self::$db->error;
	}

	// Return last insert ID
	public static function getInsertID () {
		return self::$db->insert_id;
	}
}