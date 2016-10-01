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

	}
?>