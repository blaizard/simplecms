<?php
	/**
	 * Main entry point for the framework
	 */

	/* Disable sessions Transfer SID */
	ini_set("url_rewriter.tags", "");
	session_set_cookie_params(0);
	session_cache_limiter('private_no_expire, must-revalidate');
	session_cache_expire(30);
	if (!session_start()) {
		echo "Session error, cannot initialize.";
		die();
	}

	/**
	 * Define the root path, by defautl it is 3 directory up from this file.
	 * It must end up with a \\ or /
	 */
	if (!defined('IRMCS_ROOT')) {
		define('IRCMS_ROOT', realpath(dirname(__FILE__).DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
	}
	/**
	 * Define the http path, by default it is the root of the domain.
	 * It must end up with a /
	 */
	if (!defined('IRCMS_HTTP')) {
		$document_root = IrcmsPath::clean(substr(realpath(dirname(__FILE__)), strlen(realpath($_SERVER['DOCUMENT_ROOT']))).DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR);
		define('IRCMS_HTTP', ((isset($_SERVER['HTTPS']))?"https://":"http://").$_SERVER['HTTP_HOST'].$document_root);
	}

	/**
	 * Force HTTPS if this is required
	 */
	if (defined('IRCMS_CONF_FORCE_HTTPS') && !isset($_SERVER['HTTPS'])) {
		header("Location: ".str_replace("http://", "https://", HTTP));
		die();
	}

	/* Set default configuration */
	define('IRCMS_DEBUG', (defined('IRCMS_CONF_DEBUG')) ? IRCMS_CONF_DEBUG : false);
	define('IRCMS_CACHE', (defined('IRCMS_CONF_CACHE')) ? IRCMS_CONF_CACHE : false);
	define('IRCMS_ACCESS', (defined('IRCMS_CONF_ACCESS')) ? IRCMS_CONF_ACCESS : true);
	define('IRCMS_DATA', realpath(IRCMS_ROOT.DIRECTORY_SEPARATOR.((defined('IRCMS_CONF_DATA')) ? IRCMS_CONF_DATA : './')).DIRECTORY_SEPARATOR);
	define('IRCMS_ADMIN', realpath(IRCMS_ROOT.DIRECTORY_SEPARATOR.((defined('IRCMS_CONF_ADMIN')) ? IRCMS_CONF_ADMIN : '.ircms/page/admin')).DIRECTORY_SEPARATOR);
	define('IRCMS_NOTFOUND', realpath(IRCMS_ROOT.DIRECTORY_SEPARATOR.((defined('IRCMS_CONF_NOTFOUND')) ? IRCMS_CONF_NOTFOUND : '.ircms/page/notfound')).DIRECTORY_SEPARATOR);
	define('IRCMS_THEME', realpath(IRCMS_ROOT.DIRECTORY_SEPARATOR.((defined('IRCMS_CONF_THEME')) ? IRCMS_CONF_THEME : '.ircms/theme/default')).DIRECTORY_SEPARATOR);
	define('IRCMS_INDEX', (defined('IRCMS_CONF_INDEX')) ? IRCMS_CONF_INDEX : 'index.html');
	define('IRCMS_PASSWORD', (defined('IRCMS_CONF_PASSWORD')) ? IRCMS_CONF_PASSWORD : '.ircmspwd');
	define('IRCMS_CONTENT', (defined('IRCMS_CONF_CONTENT')) ? IRCMS_CONF_CONTENT : 'content.txt');

	class Ircms {

		/* The environement context */
		private $_env;
		private $_page;

		/**
		 * Initializes the framework
		 * \return 0 in case of success, a string if the file needs to be sourced.
		 * Note, this cannot be done within the function to ensure a proper variable scope for the php scripts.
		 */
		public function __construct($path) {

			/* Initialize the environment */
			$this->_env = new IrcmsEnv($path);

			/* If this is a file, it needs to be sourced */
			if (is_file($this->_env->get("fullpath", "current"))) {
				$this->_source($this->_env->get("fullpath", "current"));
				die();
			}

			/* Initialize the page associated to it */
			$this->_page = new IrcmsPage($this->_env);
		}

		/**
		 * This function loads the data associated to the environment
		 */
		public function load() {
			$content = new IrcmsContent($this->_env);
			return $content->read();
		}

		/**
		 * Generate the page
		 */
		public function generate() {
			$data = $this->load();
			return $this->_page->generate($data);
		}

		/**
		 * Exclusively for debug, this outputs the internal variables of this object
		 */
		public function dump() {
			$dump = "// ****** IrcmsEnv Object ******\n".$this->_env->dump()."\n";
			$dump .= "// ****** IrcmsPage Object ******\n".$this->_page->dump()."\n";
			//$dump .= "// ****** IrcmsContent Object ******\n".var_export($this->load(), true)."\n";
			return $dump;
		}

		/**
		 * This function source a file from its path
		 */
		private function _source($file) {
			/* Look for the type of file */
			$info = pathinfo($file);
			$ext = strtolower($info["extension"]);

			/* Handles special cases */
			switch ($ext) {
			/* If this is a PHP script */
			case "php":
			case "php5":
				/* Update the include path */
				set_include_path($info['dirname']);
				/* Update the $_SERVER global variable */
				$exploded_uri = explode("?", $_SERVER["REQUEST_URI"]);
				$_SERVER = array_merge($_SERVER, array(
    				"SCRIPT_FILENAME" => $file,
    				"REDIRECT_QUERY_STRING" => $exploded_uri[1],
    				"REDIRECT_URL" => $exploded_uri[0],
    				"QUERY_STRING" => $exploded_uri[1],
    				"SCRIPT_NAME" => $exploded_uri[0],
    				"ORIG_PATH_INFO" => $exploded_uri[0],
    				"ORIG_PATH_TRANSLATED" => $file,
    				"PHP_SELF" => $exploded_uri[0]
    			));
				include($info['basename']);
				break;
			/* Otherwise just transfer the content of the file */
			default:
				/* Identify the MIME type */
				$mime = array(
					"css" => "text/css",
					"js" => "text/javascript",
					"jpg" => "image/jpeg",
					"json" => "application/json"
				);
				if (isset($mime[$ext])) {
					$mime_type = $mime[$ext];
				}
				else {
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
			    	$mime_type = finfo_file($finfo, $file);
					finfo_close($finfo);
				}
				/* Send the right content type according to the file */
				header("Content-type: ".$mime_type);
				/* Send the file content */
				readfile($file);
			}
		}
	}
?>
