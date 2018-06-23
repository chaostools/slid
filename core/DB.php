<?php
/**
 * Contains the DB class
 */

/**
 * Class DB
 *
 * Handles Database stuff like executing queries.
 */
class DB {
	/**
	 * Holds the mysqli class
	 *
	 * @var mysqli
	 */
	public static $db;

	/**
	 * Contains the cached mysql queries got from the query files in queries/
	 *
	 * @var array
	 */
	private static $queries = [];

	/**
	 * Contains the grouped mysql queries
	 *
	 * @var array
	 */
	private static $groups = [];

	/**
	 * Connect to the mysql server configured in index.php
	 * Called by DB::query to ensure that a connection to the database is only etablished when needed.
	 * Does nothing if already connected.
	 * Outputs error to javascript console if connection failed.
	 *
	 * @return void
	 */
	public static function connect () {
		if (self::$db) return; // Do not connect if already connected

		self::$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

		if (self::$db->connect_error) {
			View::error(500, 'Database Connection Error');
			View::console('Error (' . self::$db->connect_errno . '): ' . self::$db->connect_error, 'error');
		}
	}

	/**
	 * Closes the database connection.
	 * Does nothing if not connected.
	 * This method can be called in the finalize() function for example.
	 *
	 * @return void
	 */
	public static function close () {
		if (!self::$db) return; // Do not close if not connected

		self::$db->close();
	}

	/**
	 * Converts a multidimensional array to a single-dimensional array.
	 * For example:
	 * ```php
	 * [
	 *   "test" => "value",
	 *   "testArray" => [
	 *     "num" => 12,
	 *     "bool" => true
	 *   ]
	 * ]
	 * ```
	 * converts to:
	 * ```php
	 * [
	 *   "test" => "value",
	 *   "testArray.num" => 12,
	 *   "testArray.bool" => true
	 * ]
	 * ```
	 *
	 * @param array  $arr    The array to flatten to single-dimension
	 * @param string $prefix Don't mind this parameter, it's just needed for recursion
	 *
	 * @return array|bool The flattened (single-dimensional) array
	 */
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

	/**
	 * Read a query file containing mysql queries, convert it to a single-dimensional array (see DB::flattenArray)
	 * and cache it in DB::$queries
	 *
	 * @used-by DB::getQuery
	 *
	 * @param string $queryFile The name of the json file (query file) containing the mysql queries. Don't use a file
	 *                          extension! (No .json)
	 *
	 * @return array The flattened array containing the mysql queries of the requested file
	 */
	public static function getQueriesFromFile ($queryFile) {
		if (!isset(self::$queries[$queryFile])) {
			$rawQueries = json_decode(file_get_contents(QUERIES_DIR . $queryFile . '.json'), true);
			$queries = self::flattenArray($rawQueries);

			self::$queries[$queryFile] = $queries;
		}

		return self::$queries[$queryFile];
	}

	/**
	 * Get a mysql query
	 *
	 * @used-by DB::query
	 *
	 * @param string $queryName The name of the query. Looks like this: ``queryfile.category.subcategory.key``. The
	 *                          query file is extracted from the name and the rest is basically a path to the wanted query in
	 *                          the multidimensional array in the query file.
	 *
	 * @return string The requested mysql query
	 */
	private static function getQuery ($queryName) {
		$queryFile = explode('.', $queryName)[0];
		$queryName = substr($queryName, strlen($queryFile . '.'));

		$queries = self::getQueriesFromFile($queryFile);

		return $queries[$queryName];
	}

	/**
	 * Execute a query.
	 *
	 * @param string $queryName The name of the query. See DB::getQuery for details.
	 * @param array  $vars      The variables to replace in the query. The variables are automatically escaped and
	 *                          therefore not vulnerable to mysql injections. Variables in a query look like this {key}
	 *                          and are then replaced by the value
	 * @param string $group     If this parameter is set, then the query won't get executed but only added to the
	 *                          specified group. See DB::executeGroup() for details.
	 *
	 * @return bool|mysqli_result False, if the query wasn't found, true if $group was set and the query was
	 *                            successfully added or just a mysqli_result object if the query got successfully executed.
	 */
	public static function query ($queryName, $vars = [], $group = '') {
		self::connect();

		$query = self::getQuery($queryName);

		if (!$query)
			return false;

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

			self::$groups[$group][] = $query;

			return true;
		}

		return self::rawQuery($query);
	}

	/**
	 * Execute all queries stored in a group.
	 * Groups can be useful sometimes. For example to execute all the mysql calls only at the end of the execution.
	 *
	 * @param string $groupName The name of the group where the queries are stored.
	 *
	 * @return bool False if the group wasn't found, or true if all queries are executed. Does not return mysql results!
	 */
	public static function executeGroup ($groupName) {
		if (!isset(self::$groups[$groupName]))
			return false;

		foreach (self::$groups[$groupName] as $query) {
			self::rawQuery($query);
		}

		return true;
	}

	/**
	 * Perform a raw query.
	 * This method connects to the mysql server (if not already connected) and then executes a query.
	 * Used by DB::query and DB::executeGroup.
	 *
	 * @param string $query The mysql query
	 *
	 * @return mysqli_result The mysqli_result object
	 */
	public static function rawQuery ($query) {
		self::connect();

		return self::$db->query($query);
	}

	/**
	 * Get all rows returned by a query as an array
	 *
	 * @param mysqli_result $result The result of a query
	 *
	 * @return array The array containing all the rows
	 */
	public static function getRows ($result) {
		$rows = [];

		if ($result->num_rows > 0) {
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * This is just a wrapper for $mysqli->error.
	 *
	 * @return string The error
	 */
	public static function getError () {
		return self::$db->error;
	}

	/**
	 * Return the last inserted ID.
	 * Useful in APIs to tell the client which id the created row got.
	 * Basically a wrapper for $mysqli->insert_id
	 *
	 * @return mixed The last inserted ID. Usually a integer. Zero if there is no previous query or if the
	 *               AUTO_INCREMENT value wasn't updated.
	 */
	public static function getInsertID () {
		return self::$db->insert_id;
	}
}