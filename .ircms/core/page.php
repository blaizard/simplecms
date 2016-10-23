<?php
	/**
	 * This value contains the location where the header section in the document is located
	 *
	 * Important: It must be escapped with 'htmlentities' to avoid conflict with the 'editors'.
	 */
	define('IRCMS_HEADER_TAG', "IRCMSHEADER");

	class IrcmsPage {

		private $_env;
		private $_page;
		private $_cssHeader;
		private $_jsHeader;
		private $_header;
		private $_cache;

		/**
		 * This function generates the page content from en environment object.
		 * It will also fix and add some needed keywords if missing.
		 *
		 * \param env The environment variable
		 */
		public function __construct($env, $cache) {

			/* Initialize some of the variables */
			$this->_cssHeader = array();
			$this->_jsHeader = array();
			$this->_header = array();

			/* Save the environement object */
			$this->_env = $env; 

			/* Save the cache object */
			$this->_cache = $cache;

			/* Look for the page, the first one from the current directory */
			if (!($page = $this->fetchIndex())) {
				return false;
			}

			/* Update the environment path */
			$env->add("fullpath", "index", dirname($page).DIRECTORY_SEPARATOR);
			$env->add("url", "index", IrcmsPath::concat($env->get("url", "root"), substr(dirname($page), strlen($env->get("fullpath", "root")))."/"));

			/* Save the page location */
			$this->_page = $page;
		}

		/**
		 * Retrieve the environement variable associated with the page
		 */
		public function env() {
			return $this->_env;
		}

		/**
		 * Return the cache instance, this can be usefull if it needs to be passed to another module
		 * for example.
		 */
		public function cache() {
			return $this->_cache;
		}

		/**
		 * Add a CSS file path to the list
		 */
		public function addCssHeader($path) {
			array_push($this->_cssHeader, $path);
		}

		/**
		 * Add a Javascript file path to the list
		 */
		public function addJsHeader($path) {
			array_push($this->_jsHeader, $path);
		}

		/**
		 * Add an HTML header to the list
		 */
		public function addHeader($str) {
			array_push($this->_header, $str);
		}

		/**
		 * Return the page content
		 */
		public function generate($data = array()) {

			/* Reset the path history */
			$this->_pathHistory = array($this->_page);
			$this->_pathHistoryTemplate = array($this->_page);

			// Create the content interface for the page
			$pageContent = new IrcmsPageContent($this, $data);

			/* Add custom headers */
			$this->generateCustomHeaders();

			/* Postpone the standard output */
			ob_start();
			/* Process the page */
			$pageContent->fileInclude($this->_page);
			/* Set the output into "$ob_output" */
			$content = ob_get_contents();
			ob_end_clean();

			/* Sanity check */
			/* Make sure the IRCMS_HEADER_TAG tag is present, otherwise, set it */
			if (strpos($content, "<".IRCMS_HEADER_TAG."/>") === false) {
				/* Insert it at the end of the <head/> tag */
				$content = preg_replace("_</\s*head\s*>_si", "\n<".IRCMS_HEADER_TAG."/>\n</head>", $content, 1);
			}

			/* Add the javascript headers */
			$path_list = (IRCMS_DEBUG) ? $this->_jsHeader : $this->_jsHeader; //ircms_compress_js($env, $this->_jsHeader);
			$path_list = array_unique($path_list);
			foreach ($path_list as $path) {
				$this->addHeader("<script type=\"text/javascript\" src=\"".$this->_env->toUrl($path)."\"></script>");
			}
			/* Add the CSS headers - NOTE: CSS cannot be minified, as CSS url is relative the CSS stylesheet */
			$path_list = (IRCMS_DEBUG) ? $this->_cssHeader : $this->_cssHeader; //ircms_compress_css($env, $this->_cssHeader);
			$path_list = array_unique($path_list);
			foreach ($path_list as $path) {
				$this->addHeader("<link rel=\"stylesheet\" type=\"text/css\" href=\"".$this->_env->toUrl($path)."\"/>");
			}

			/* Write headers */
			$content = $this->replaceTags($content, array(
				IRCMS_HEADER_TAG => implode("\n", array_unique($this->_header))
			));

			/* Return the page content */
			return $content;
		}

		/**
		 * Return the current path
		 */
		public function getCurrentPath() {
			return dirname(end($this->_pathHistory)).DIRECTORY_SEPARATOR;
		}

		/**
		 * Return the current path from the template dir (if any)
		 */
		public function getCurrentPathTemplate() {
			return dirname(end($this->_pathHistoryTemplate)).DIRECTORY_SEPARATOR;
		}

		/**
		 * Push a new current path to the list
		 */
		public function pushCurrentPath($path) {
			array_push($this->_pathHistory, $path);
		}

		/**
		 * Remove the latest added current path from the list
		 */
		public function popCurrentPath() {
			return array_pop($this->_pathHistory);
		}

		/**
		 * Push a new current path to the list
		 */
		public function pushCurrentPathTemplate($path) {
			array_push($this->_pathHistoryTemplate, $path);
		}

		/**
		 * Remove the latest added current path from the list
		 */
		public function popCurrentPathTemplate() {
			return array_pop($this->_pathHistoryTemplate);
		}

		/**
		 * Generate custom headers
		 */
		private function generateCustomHeaders() {
			// Add the base header
			$this->addHeader("<base href=\"".$this->env()->get("url", "current")."\"/>");
		}

		/**
		 * Exclusively for debug, this outputs the internal variables of this object
		 */
		public function dump() {
			$dump = "$"."_cssHeader = ".var_export($this->_cssHeader, true)."\n";
			$dump .= "$"."_jsHeader = ".var_export($this->_jsHeader, true)."\n";
			$dump .= "$"."_header = ".var_export($this->_header, true)."\n";
			return $dump;
		}

		/**
		 * Replace a tag with its value.
		 * A tag is formed as follow: "%keyword%", where keyword is a alphanumeric, uppercase value.
		 *
		 * \param str the string which contains the tags
		 * \param tag_list The listof the tags. It must be an array where the keys correspond to the
		 *      keywords and the values to the values of the keywords.
		 *      A value can be an array, in that case, the first element of this array will be assigned
		 *      to the first keyword found, the second, to the seconf keyword found, and so on.
		 * \param required If this variable is set to true, then it means that at least one tag must be replaced,
		 *      otherwise an error will be returned.
		 *
		 * \return The new string.
		 */
		private function replaceTags($str, $tag_list, $required = false) {
			foreach ($tag_list as $tag => $value) {
				$count = 0;
				/* Look for existing tags */
				$str = preg_replace_callback("/<".$tag."\/>/si", function ($matches) use ($value, $tag, &$count) {
					/* Find the value from the list */
					if (is_array($value)) {
						$value = (isset($value[$count])) ? $value[$count] : null;
					}
					/* If the value is null, don't replace */
					if (is_null($value)) {
						$value = "%".$tag."%";
					}
					/* Increase the counter */
					$count++;
					return $value;
				}, $str);
				/* Make sure the required field have been updated */
				if ($required && !$count) {
					throw new Exception("The tag `".$tag."' could not be found.");
				}
			}
			return $str;
		}

		/**
		 * This function fetch the index used by the page
		 */
		private function fetchIndex() {
			/* Look for the page, the first one from the current directory */
			$path = IrcmsPath::find($this->_env->get('fullpath', 'current'), $this->_env->get('fullpath', 'data'), IRCMS_INDEX);
			/* Returns an error if nothing is found */
			if (!$path) {
				/* If path is root, path, show the getting started page */
				if ($this->_env->get('path') == DIRECTORY_SEPARATOR) {
					return IrcmsPath::concat($this->_env->get('fullpath', 'current'), IRCMS_GETTINGSTARTED);
				}
				throw new Exception("Cannot find the template `".IRCMS_INDEX."' in `".$this->_env->get('fullpath', 'current')."'");
			}

			return $path;
		}
	}
?>
