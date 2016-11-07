<?php

	/**
	 * Utility class to handle path
	 */
	class IrcmsUrl {

		/**
		 * Convert an URL into a path
		 */
		public static function toPath($url) {
			/* Path pointing to the file or directoy (i.e. removing every arguments) */
			$path = strtok(strtok($url, "?"), "#");
			return IrcmsPath::clean("/".$path);
		}

		/**
		 * Concatenantes a list of URLs, dealing with the '/'
		 */
		public static function concat() {
			$fullurl = "";
			foreach (func_get_args() as $url) {
				$fullurl = ($fullurl) ? rtrim($fullurl, "/")."/".ltrim($url, "/") : $url;
			}
			return $fullurl;
		}

		/**
		 * Generate a usable URL name from a random string. It removes all strange characters
		 * replaces spaces by '-' (for SEO) and set everything to lowercase.
		 */
		public static function fromString($string) {
			/* Replace non letters or digits by '-' */
			$string = preg_replace('/[^\\pL\d]+/u', '-', $string);

			/* Replace all accents */
			if (function_exists('iconv')) {
				setlocale(LC_ALL, 'en_US.UTF8');
				$string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
			}

			/* Set the string to lower case, to avoid issue with Windows */
			$string = strtolower($string);

			/* Remove unwanted characters */
			$string = preg_replace('/[^-\w]+/', '', $string);

			/* Remove '-' at the begining or end */
			$string = trim($string, '-');

			/* Return the new name */
			return $string;
		}

		/**
		 * Redirect the current page to the specified URL
		 */
		public static function redirect($url) {
			header("Location: ".$url);
			die();
		}
	}
?>