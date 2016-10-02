<?php
	class IrcmsEnv {

		/* The environement context */
		private $_env;

		/**
		 * Fetch and generates the environement variable.
		 *
		 * \param path The current directory path
		 *
		 * \return The environment variable.
		 */
		public function __construct($path = DIRECTORY_SEPARATOR) {
			/* Initialize the main variable which contains the environment info */
			$this->_env = array();
			/* Read the current path argument if any */
			$path = IrcmsUrl::toPath($path);
			/* If the file does not exists */
			if (!@file_exists(IRCMS_DATA.$path)) {
				header("Location: ".IRCMS_HTTP."404?url=".urlencode(IrcmsUrl::concat(IRCMS_HTTP, $_SERVER['REQUEST_URI'])));
				die();
			}
			/* Add DIRECTORY_SEPARATOR on the right of the path if this is a directory */
			$path = rtrim($path, DIRECTORY_SEPARATOR).((is_dir(IRCMS_DATA.$path)) ? DIRECTORY_SEPARATOR : "");

			/* Check access */
			if (IRCMS_ACCESS && !ircms_access_check($path)) {
				header("Location: ".ircms_url("/access?redirect=".urlencode($_SERVER['REQUEST_URI'])));
				die();
			}

			/* Create the environement variable */
			$this->_env['path'] = $path;
			/* Full path */
			$this->_env['fullpath'] = array(
				/* Where the .ircms is */
				'root' => IRCMS_ROOT,
				/* Where the data directoy is */
				'data' => IRCMS_DATA,
				/* Where the cache directoy is */
				'cache' => IRCMS_CACHE,
				/* Where the admin directoy is */
				'admin' => IRCMS_ADMIN,
				/* Where the theme is located */
				'theme' => IRCMS_THEME,
				/* Where the current file/directoy is */
				'current' => IrcmsPath::concat(IRCMS_DATA, $path),
				/* Where the local content file is */
				'content' => IrcmsPath::concat(IRCMS_DATA, $path, IRCMS_CONTENT),
				/* Where the password file is */
				'pwd' => IrcmsPath::concat(IRCMS_ROOT, IRCMS_PASSWORD),
			);
			/* URLs */
			$this->_env['url'] = array(
				/* Top level URL */
				'root' => IRCMS_HTTP,
				/* Cache handler is */
				'cache' => IrcmsPath::concat(IRCMS_HTTP, 'cache.php'),
				/* Current URL */
				'current' => IrcmsPath::concat(IRCMS_HTTP, IrcmsPath::toUrl($path))
			);
		}

		/**
		 * Get a unique representation of the environement.
		 * This can be used as an ID for the cache for example.
		 * Note json_encode is used instead of serialize because it is more than twice faster.
		 */
		public function id() {
			return md5(json_encode($this->get()).json_encode($_GET).json_encode($_POST));
		}

		/**
		 * Add a value to the environment
		 */
		public function add($arg1, $arg2, $arg3 = null) {
			if ($arg3) {
				$this->_env[$arg1][$arg2] = $arg3;
			}
			else {
				$this->_env[$arg1] = $arg2;
			}
		}

		/**
		 * Return the environement data
		 */
		public function get($key1 = null, $key2 = null) {
			if ($key2) {
				return $this->_env[$key1][$key2];
			}
			if ($key1) {
				return $this->_env[$key1];
			}
			return $this->_env;
		}

		/**
		 * This function converts a string into a valid URL. If the string is a path make sure it exists, and if it is
		 * already an URL return it. Otherwise return null.
		 *
		 * \param path The string to convert into an URL. This string can be
		 * a realtive path, an absolute path or an URL.
		 * \param [current_path] An optional path where the relative path should happened.
		 *
		 * \return a string contianing the URL.
		 */
		public function toUrl($str) {
			/* If there is no exception */
			if (file_exists($str)) {
				/* Returns the URL from the path */
				$path = IrcmsPath::getRelativePath($this->get("fullpath", "data"), $str, true);
				return $this->get("url", "root").IrcmsPath::toUrl($path);
			}
			/* If the string is an URL */
			else if (preg_match("_^\s*(http://|https://|www\.)_", $str)) {
				return $str;
			}
			/* Otherwise this is not a valid path */
			throw new Exception("`".$str."' is nor a valid path, nor a valid URL. Cannot be converted into an URL.");
		}

		/**
		 * This function returns an absolute path from the path passed into argument
		 *
		 * \param path The path. It can be a realtive path or an absolute path.
		 * \param [current_path] An optional path where the relative path should happened.
		 *                       This can also be an array of path.
		 *
		 * \return The absolute path or null in case of error.
		 */
		public function toPath($path, $current_path = null) {
			/* Check if it is in the current path */
			if (file_exists($this->get("fullpath", "current").$path)) {
				return IrcmsPath::concat($this->get("fullpath", "current"), $path);
			}
			/* Check if it is relative to the current path passed in argument */
			if ($current_path) {
				$current_path_list = (is_array($current_path)) ? $current_path : array($current_path);
				foreach ($current_path_list as $current_path) { 
					if (file_exists($current_path.DIRECTORY_SEPARATOR.$path)) {
						return IrcmsPath::concat($current_path, $path);
					}
				}
			}
			/* Check if it is relative to the root directory */
			if (file_exists($this->get("fullpath", "data").$path)) {
				return IrcmsPath::concat($this->get("fullpath", "data"), $path);
			}
			/* If it is already an absolute path, do nothing */
			if (file_exists($path)) {
				return $path;
			}
			return false;
		}

		/**
		 * Exclusively for debug, this outputs the internal variables of this object
		 */
		public function dump() {
			return "$"."_env = ".var_export($this->_env, true);
		}
	}
?>
