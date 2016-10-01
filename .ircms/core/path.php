<?php

	/**
	 * Utility class to handle path
	 */
	class IrcmsPath {

		/**
		 * Convert a standard path into an URL
		 * Example: /root/this dir/path -> /root/this%20dir/path
		 */
		public static function toUrl($path) {
			$split = explode("/", $path);
			foreach ($split as &$directory) {
				$directory = urlencode($directory);
			}
			return implode("/", $split);;
		}

		/**
		 * This function build a path from a top path and a relative path.
		 * It will ensure that the resulting path does not go above the top path.
		 */
		public static function appendSubPath($top_path, $relative_path) {
			/* Generate the top directory path*/
			if (($top = realpath($top_path)) === false) {
				return exception_throw("The path `".$top_path."' does not exists.");
			}
			/* Build the complete new path */
			if (($complete = realpath($top."/".$relative_path)) === false) {
				return exception_throw("The path `".$top."/".$relative_path."' does not exists.");
			}
			/* Make sure the complete path is within the top path, it must be found at position 0  */
			if (strpos($complete, $top) === 0) {
				return $complete;
			}
			/* If not, it means that the top path went above, then returns the top path */
			return $top;
		}

		/**
		 * Create a directory (if it already exists, do nothing)
		 */
		public static function createDir($dirpath) {
			if (is_dir($dirpath)) {
				return;
			}
			/* Create a directory */
			if (mkdir($dirpath) === false) {
				return exception_throw("Error while creating the directory `".$dirpath."'");
			}
		}

		/**
		 * Generate a usable path name from a random string. It removes all strange characters
		 * replaces spaces by '-' (for SEO) and set everything to lowercase.
		 */
		public static function fromString($name) {
			/* Replace non letters or digits by '-' */
			$name = preg_replace('/[^\\pL\d]+/u', '-', $name);

			/* Replace all accents */
			if (function_exists('iconv')) {
				setlocale(LC_ALL, 'en_US.UTF8');
				$name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
			}

			/* Set the string to lower case, to avoid issue with Windows */
			$name = strtolower($name);

			/* Remove unwanted characters */
			$name = preg_replace('/[^-\w]+/', '', $name);

			/* Remove '-' at the begining or end */
			$name = trim($name, '-');

			/* Make sure the directory name is longer than 1 character */
			if (strlen($name) < 1) {
				return exception_throw("The page name is too short, please change it");
			}

			/* Return the new name */
			return $name;
		}

		/**
		 * This function cleans a path (removes duplicated '/', extra '..', ...)
		 */
		public static function clean($path) {
			/* Convert all '\' into '/' */
			$path = str_replace('\\', '/', $path);
			$result = array();
			$pathA = explode('/', $path);
			if (!$pathA[0]) {
				$result[] = '';
			}
			foreach ($pathA AS $key => $dir) {
				if ($dir == '..') {
					if (end($result) == '..') {
						$result[] = '..';
					}
					else if (!array_pop($result)) {
						$result[] = '..';
					}
				}
				else if ($dir && $dir != '.') {
					$result[] = $dir;
				}
			}
			if (!end($pathA)) {
				$result[] = '';
			}
			$result = implode('/', $result);
			return $result;
		}

		/**
		 * Concatenantes a list of path, dealing with the '/'
		 */
		public static function concat() {
			$fullpath = "";
			foreach (func_get_args() as $path) {
				$fullpath = ($fullpath) ? rtrim($fullpath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : $path;
			}
			return $fullpath;
		}

		/**
		 * This function generates a relative path from 2 valid path
		 *
		 * \param root_path The base path, from where the relative path should
		 *  be built
		 * \param path The path that needs to be processed
		 * \param clean Set to false if the path are not clean (i.e. if they might have ../ or multiple //)
		 *
		 * \return The relative path from the root path
		 */
		public static function getRelativePath($root_path, $path, $clean = false) {
			$r = $root_path;
			$p = $path;
			/* Clean-up the path */
			if (!$clean && ($p = realpath($path)) === false) {
				return exception_throw("The path `".$path."' does not exists.");
			}
			if (!$clean && ($r = realpath($root_path)) === false) {
				return exception_throw("The path `".$root_path."' does not exists.");
			}
			/* Replace all the "\" by "/" */
			$p = trim(str_replace("\\", "/", $p), " \t\n\r\0\x0B/");
			$r = trim(str_replace("\\", "/", $r), " \t\n\r\0\x0B/");
			/* Explode the path */
			$root_exploded = explode("/", $r);
			$path_exploded = explode("/", $p);
			/* This is the resulting exploded path */
			$relative_exploded = $path_exploded;
			/* Loop through */
			$differ = false;
			foreach ($root_exploded as $index => $dirname) {
				/* If the path is shorted than the root path or the 2 directories are different */
				if ($differ || !isset($path_exploded[$index]) || $dirname != $path_exploded[$index]) {
					array_unshift($relative_exploded, "..");
					$differ = true;
				}
				/* Check if this directory is common to both path */
				else {
					array_shift($relative_exploded);
				}
			}

			return implode("/", $relative_exploded);
		}

		/**
		 * This function will try to find the first file named "file_name",
		 * starting from the directory "path" until the top level directory "path_top".
		 *
		 * \param path The directory path from where to start the research
		 * \param path_top The top-level directory path from where to stop the research
		 * \param file_name The name of the file to look for
		 *
		 * \return The path of the directory containing the file; or null if not found
		 */
		public static function find($path, $path_top, $file_name) {
			$path = IrcmsPath::clean($path);
			$path_top = IrcmsPath::clean($path_top);

			/* If the path is higher than the top path */
			if (preg_match("/.*\.\.$/", $path) || strlen($path) < strlen($path_top)) {
				return null;
			}

			/* If the file has been found */
			if (file_exists($path.$file_name) && is_file($path.$file_name)) {
				return $path;
			}

			/* If the path is still deep inside the top path */
			return IrcmsPath::find($path."/../", $path_top, $file_name);
		}

		/**
		 * This function fetch the files in a contained directory tree architecture
		 *
		 * \param path The directory path from where to start the research
		 * \param path_top The top-level directory path from where to stop the research
		 * \param file_name The name of the file to look for
		 *
		 * \return An array containing the path of the files found
		 */
		public static function findMulti($path, $path_top, $file_name) {
			$file_list = array();
			do {
				/* Look for the custom templates */
				$path = IrcmsPath::find($path, $path_top, $file_name);
				/* If nothing has been found, just quit */
				if (!$path) {
					break;
				}
				/* Add this path to the list */
				array_push($file_list, $path.$file_name);
				/* Look in the parent folder */
				$path = $path."/../";
			} while ($path != "/../");

			/* Return the list of files */
			return $file_list;
		}

		/**
		 * This function explodes a path and returns an array of the path names
		 * plus their path.
		 * For example, "/etc/pass/test/" becomes:
		 * array(
		 *      array("/", "/"),
		 *      array("etc/", "/etc/"),
		 *      array("pass/", "/etc/pass/"),
		 *      array("test/", "/etc/pass/test/"),
		 * );
		 */
		public static function breadcrumb($path) {
			$list = explode("/", $path);
			/* Remove the last entry if empty */
			if (!end($list)) {
				array_pop($list);
			}
			$current_path = "";
			$result = array();
			foreach ($list as $item) {
				$current_path .= $item."/";
				array_push($result, array($item."/", $current_path));
			}
			return $result;
		}
	}
?>