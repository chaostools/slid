<?php

if (TEMPLATES) {
	require_once "core/simplates.php";
}


class Slid {
	private $routes = array();

	public function __construct () {
		$this->routes = $this->getRoutes();
	}

	private function getDirectory($path = '') {
		$result = [];
		$scan = glob($path . '*');

		foreach($scan as $item){
			if(is_dir($item))
				$result[basename($item)] = $this->getDirectory($item . '/');
			else
				$result[] = basename($item);
		}

		return $result;
	}

	private function getPaths($dir, $prefix = '') {
		if (!is_array($dir)) {
			return false;
		}

		$result = array();
		foreach ($dir as $key => $value) {
			if ($prefix == "") {
				$newPrefix = $key;
			} else {
				$newPrefix = $prefix . '/' . $key;
			}

			if (is_array($value)) {
				$result = array_merge($result, $this->getPaths($value, $newPrefix));
			} else {
				$result[] = $newPrefix . $value;
			}
		}

		return $result;
	}

	private function getRoutes() {
		$dir = $this->getDirectory(ROUTES_DIR);
		$paths = $this->getPaths($dir);

		print_r($dir);
		print_r($paths);
	}

	public function run() {

	}
}


// STATIC:

class DB {

}


class View {

}