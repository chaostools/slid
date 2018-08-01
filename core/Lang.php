<?php
/**
 * Contains the Lang class
 */


/**
 * Class Lang
 *
 * Handles Translation.
 */
class Lang {

	public static $language = '';
	private static $translation = [];

	/* Set Language and read the translation file */
	public static function setLang($lang) {
		if(isset(self::$translation[$lang])) {
			self::$language = $lang;
			return true;
		}

		if(file_exists(LANG_DIR . $lang . '.json')) {
			$jsonData = json_decode(file_get_contents(LANG_DIR . $lang . '.json'), true);

			if($jsonData) {
				self::$translation[$lang] = $jsonData;
				self::$language = $lang;

				return true;
			} else {
				View::console('Error: Unable to decode JSON in Translation File. Please check!' , 'error');
				return false;
			}
		} else {
			View::console('Error: Translation File for language "' . $lang . '" not found.' , 'error');
			return false;
		}
	}

	public static function t($key, $variables = [], $plural = false) {
		if (self::$language == '') {
			$success = self::setLang(DEFAULT_LANG);

			if (!$success) {
				View::console('Error: Translation for the default language not found.', 'error');
			}
		}

		if(isset(self::$translation[self::$language][$key])) {
			$string = self::$translation[self::$language][$key];

			if(is_array($string)) {
				if($plural) {
					$string = self::$translation[self::$language][$key][1];
				} else {
					$string = self::$translation[self::$language][$key][0];
				}
			}

			preg_match_all('/\{(\w+)\}/', $string, $varReplacements);

			foreach ($varReplacements[1] as $varName) {
				if (!isset($variables[$varName])) {
					continue;
				}

				$string = str_replace('{' . $varName . '}', $variables[$varName], $string);

				return $string;
			}
		}

		return false;
	}
}