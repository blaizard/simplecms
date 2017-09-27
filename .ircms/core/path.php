<?php

	/**
	 * Utility class to handle path
	 */
	class IrcmsPath {

		/**
		 * Options for the \ref find function
		 */
		const FIND_EXCLUDE_TOP = 1;
		const FIND_EXCLUDE_BOTTOM = 2;
		const FIND_FILE = 4;
		const FIND_DIRECTORY = 8;

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
				throw new Exception("The path `".$top_path."' does not exists.");
			}
			/* Build the complete new path */
			if (($complete = realpath($top."/".$relative_path)) === false) {
				throw new Exception("The path `".$top."/".$relative_path."' does not exists.");
			}
			/* Make sure the complete path is within the top path, it must be found at position 0  */
			if (strpos($complete, $top) === 0) {
				return $complete;
			}
			/* If not, it means that the top path went above, then returns the top path */
			return $top;
		}

		/**
		 * This function cleans a path (removes duplicated '/', extra '..', ...)
		 */
		public static function clean($path) {
			// Handle corner case
			if (!$path) {
				return "";
			}
			// Convert all '\' into '/'
			$path = str_replace('\\', '/', $path);

			$result = array();
			$pathA = explode('/', $path);
			$relative = ($pathA[0]) ? true : false;
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
			$result = implode(DIRECTORY_SEPARATOR, $result);
			return ($relative) ? $result : DIRECTORY_SEPARATOR.$result;
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
		 * Compare 2 path
		 *
		 * \return 0 if the path are the same
		 */
		public static function cmp($path1, $path2) {
			// Cleanup and homogenize the 2 paths
			$path1 = Self::clean($path1.DIRECTORY_SEPARATOR);
			$path2 = Self::clean($path2.DIRECTORY_SEPARATOR);

			if ($path1 == $path2) {
				return 0;
			}
			return 1;
		}

		/**
		 * This function generates a relative path from 2 valid path
		 *
		 * \param root_path The base path, from where the relative path should
		 *  be built
		 * \param path The path that needs to be processed
		 *
		 * \return The relative path from the root path or null if not related
		 */
		public static function getRelativePath($root_path, $path) {
			// Clean-up the path
			$root_path = Self::clean($root_path);
			$path = Self::clean($path);
			// Make sure path is a sub path of root_path
			if (!$path || ($pos = strpos($path, $root_path)) === false) {
				return null;
			}
			return substr($path, strlen($root_path));
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
		public static function find($path, $path_top, $file_name, $options = Self::FIND_FILE) {
			$path = Self::clean($path.DIRECTORY_SEPARATOR.(($options & Self::FIND_EXCLUDE_BOTTOM) ? "../" : "" ));
			$path_top = Self::clean($path_top.DIRECTORY_SEPARATOR);

			$result = Self::_findRec($path, $path_top, $file_name, $options);
			if (!$result) {
				return null;
			}

			// If this is the top directory
			if (($options & Self::FIND_EXCLUDE_TOP) && dirname($result).DIRECTORY_SEPARATOR == $path_top) {
				return null;
			}

			return $result;
		}
		private static function _findRec($path, $path_top, $file_name, $options = Self::FIND_FILE) {
			// If the path is higher than the top path
			if (preg_match("/.*\.\.$/", $path) || strlen($path) < strlen($path_top)) {
				return null;
			}

			// If the file has been found, assert that it is a file or a directory
			if (file_exists($path.$file_name)) {
				if ((($options & Self::FIND_FILE) && is_file($path.$file_name))
						|| (($options & Self::FIND_DIRECTORY) && is_dir($path.$file_name))) {
					return $path.$file_name;
				}
			}

			// If the path is still deep inside the top path
			return Self::_findRec(dirname($path).DIRECTORY_SEPARATOR, $path_top, $file_name, $options);
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
		public static function findMulti($path, $path_top, $file_name, $options = Self::FIND_FILE) {
			$file_list = array();
			do {
				/* Look for the custom templates */
				if (!($file = IrcmsPath::find($path, $path_top, $file_name, $options))) {
					break;
				}
				/* Add this path to the list */
				array_push($file_list, $file);
				/* Look in the parent folder */
				$path = dirname($file)."/../";
			} while ($path != "/../");

			/* Return the list of files */
			return $file_list;
		}

		/**
		 * This functions checks wether or not a path is a subpath of another.
		 * The path can be real or arbitraries.
		 *
		 * \param path The parent path
		 * \param subpath The potential sub path of \p path
		 *
		 * \return 0 if not related
		 *         1 if \p subpath is a sub-path of \p path
		 *         2 if \p subpath is equal to \p patth  
		 */
		public static function isSubPath($path, $subpath) {
			$path = Self::clean($path.DIRECTORY_SEPARATOR);
			$subpath = Self::clean($subpath.DIRECTORY_SEPARATOR);
			// If they are identical, returns 2
			if ($path == $subpath) {
				return 2;
			}
			$subpathInsidePath = (strpos($subpath, $path) === 0);
			// If path is found at the begining of subpath, then this is a match
			return ($subpathInsidePath) ? 1 : 0;
		}

		/**
		 * This function returns an absolute path from the path passed into argument.
		 * If the path is absolute, then return it. If not, then evaluate it with the
		 * base path passed into argument.
		 *
		 * \param path The path. It can be a realtive path or an absolute path.
		 * \param [relative_path] An optional path where the relative path should happened.
		 *                       This can also be an array of path.
		 * \param [absolute_path] An optional path where the absolute path should happened.
		 *                       This can also be an array of path.
		 *
		 * \return The absolute path or null in case of error.
		 */
		public static function toPath($path, $relative_path = "./", $absolute_path = "/") {
			// Convert the path into an arrays
			$relative_path_list = (is_array($relative_path)) ? $relative_path : array($relative_path);
			$absolute_path_list = (is_array($absolute_path)) ? $absolute_path : array($absolute_path);
			// Check first if the path is absolute
			if (substr($path, 0, 1) == DIRECTORY_SEPARATOR) {
				foreach ($absolute_path_list as $absolute_path) {
					if (file_exists($absolute_path.$path)) {
						return realpath($absolute_path.$path);
					}
				}
			}
			// Then if it is relative
			foreach ($relative_path_list as $relative_path) {
				if (file_exists($relative_path.DIRECTORY_SEPARATOR.$path)) {
					return realpath($relative_path.DIRECTORY_SEPARATOR.$path);
				}
			}
			// Means no match
			return null;
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
			// Remove the last entry if empty
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

		/**
		 * Return the file list located at the specified path
		 */
		public static function getFileList($path, $includeHidden = false) {
			$dir = @opendir($path);
			if (!$dir) {
				throw new Exception("Cannot open the directory `".$path."'");
			}
			$fileList = array();
			while(($file = readdir($dir)) != false) {
				if ($file != "." && $file != "..") {
					if (!$includeHidden && ($file[0] == "." || $file[0] == "@")) {
						continue;
					}
					$fileList[] = $file;
				}
			}
			closedir($dir);
			return $fileList;
		}

		/**
		 * Return the directory list located at the specified path
		 */
		public static function getDirectoryList($path, $includeHidden = false) {
			$fileList = Self::getFileList($path, $includeHidden);
			return array_filter($fileList, function($file) use ($path) {
				return is_dir($path.DIRECTORY_SEPARATOR.$file);
			});
		}
	}
?>