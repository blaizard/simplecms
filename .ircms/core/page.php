<?php
	/**
	 * This value contains the location where the header section in the document is located
	 *
	 * Important: It must be escapped with 'htmlentities' to avoid conflict with the 'editors'.
	 */
	define('IRCMS_HEADER_TAG', "IRCMSHEADER");
	define('IRCMS_BODY_TAG', "IRCMSJAVASCRIPT");

	class IrcmsPage {

		private $_env;
		private $_page;
		private $_cssHeader;
		private $_jsHeader;
		private $_header;
		private $_jsInline;
		private $_data;
		private $_pathHistory;

		/**
		 * This function generates the page content from en environment object.
		 * It will also fix and add some needed keywords if missing.
		 *
		 * \param env The environment variable
		 */
		public function __construct($env) {

			/* Initialize some of the variables */
			$this->_cssHeader = array();
			$this->_jsHeader = array();
			$this->_header = array();
			$this->_jsInline = "";
			$this->_data = array();
			$this->_pathHistory = array();

			/* Save the environement object */
			$this->_env = $env; 

			/* Look for the page, the first one from the current directory */
			if (!($page = $this->_fetchIndex())) {
				return EXCEPTION_CODE;
			}

			/* Update the environment path */
			$env->add("fullpath", "index", dirname($page).DIRECTORY_SEPARATOR);
			$env->add("url", "index", IrcmsPath::concat($env->get("url", "root"), substr(dirname($page), strlen($env->get("fullpath", "root")))."/"));

			/* Save the page location */
			$this->_page = $page;
		}

		/**
		 * Add a CSS header
		 */
		public function cssHeader($file) {
			$backtrace = debug_backtrace();
			$path = $this->_env->toPath($file, array(
				$this->_getCurrentPath(),
				$this->_env->get("fullpath", "index")
			));
			array_push($this->_cssHeader, ($path === false) ? $file : $path);
		}

		/**
		 * Add a JavaScript header
		 */
		public function jsHeader($file) {
			$backtrace = debug_backtrace();
			/* Try to convert it into a valid path */
			$path = $this->_env->toPath($file, array(
				$this->_getCurrentPath(),
				$this->_env->get("fullpath", "index")
			));
			array_push($this->_jsHeader, ($path === false) ? $file : $path);
		}

		/**
		 * Add a custom header
		 */
		public function header($item) {
			array_push($this->_header, $item);
		}

		/**
		 * Add inline JavaScript
		 */
		public function jsInline($str) {
			$this->_jsInline .= $str."\n";
		}

		/**
		 * Return the page content
		 */
		public function generate($data = array()) {

			/* Reset the path history */
			$this->_pathHistory = array($this->_page);

			/* Set the data */
			$this->_data = $data;

			/* Add custom headers */
			$this->_generateCustomHeaders();

			/* Postpone the standard output */
			ob_start();
			/* Process the page */
			include_once($this->_page);
			/* Set the output into "$ob_output" */
			$content = ob_get_contents();
			ob_end_clean();

			/* Sanity check */
			/* Make sure the IRCMS_HEADER_TAG tag is present, otherwise, set it */
			if (strpos($content, "<".IRCMS_HEADER_TAG."/>") === false) {
				/* Insert it at the end of the <head/> tag */
				$content = preg_replace("_</\s*head\s*>_si", "\n<".IRCMS_HEADER_TAG."/>\n</head>", $content, 1);
			}
			/* Make sure the IRCMS_BODY_TAG tag is present, otherwise, set it */
			if (strpos($content, "<".IRCMS_BODY_TAG."/>") === false) {
				/* Insert it at the very end of the <body/> tag */
				$content = strrev(preg_replace("_>\s*ydob\s*/<_si", ">ydob/<\n".strrev("<".IRCMS_BODY_TAG."/>")."\n", strrev($content), 1));
			}

			/* Add the javascript headers */
			$path_list = (IRCMS_DEBUG) ? $this->_jsHeader : $this->_jsHeader; //ircms_compress_js($env, $this->_jsHeader);
			$path_list = array_unique($path_list);
			foreach ($path_list as $path) {
				$this->header("<script type=\"text/javascript\" src=\"".$this->_env->toUrl($path)."\"></script>");
			}
			/* Add the CSS headers - NOTE: CSS cannot be minified, as CSS url is relative the CSS stylesheet */
			$path_list = (IRCMS_DEBUG) ? $this->_cssHeader : $this->_cssHeader; //ircms_compress_css($env, $this->_cssHeader);
			$path_list = array_unique($path_list);
			foreach ($path_list as $path) {
				$this->header("<link rel=\"stylesheet\" type=\"text/css\" href=\"".$this->_env->toUrl($path)."\"/>");
			}

			/* Write headers */
			$content = $this->_replaceTags($content, array(
				IRCMS_HEADER_TAG => implode("\n", array_unique($this->_header))
			));

			/* Write inline JavaScript */
			$content = $this->_replaceTags($content, array("IRCMSJAVASCRIPT" => ($this->_jsInline) ? "<script type=\"text/javascript\"><!--\n".$this->_jsInline."\n//--></script>" : ""));

			/* Save the page content */
			return $content;
		}

		/**
		 * Return environement variables
		 */
		public function env($key1 = null, $key2 = null) {
			return $this->_env->get($key1, $key2);
		}

		/**
		 * Return the data identified by the name
		 */
		public function data($name) {
			if (isset($this->_data[$name])) {
				return $this->_data[$name]["value"];
			}
			return "";
		}

		/**
		 * Return the arguments of the data identified by the name
		 */
		public function dataArgs($name) {
			if (isset($this->_data[$name])) {
				return json_decode($this->_data[$name]["args"]);
			}
			return array();
		}

		/**
		 * Return the path of the data identified by the name
		 */
		public function dataPath($name) {
			if (isset($this->_data[$name])) {
				return $this->_data[$name]["path"];
			}
			return null;
		}

		/**
		 * Return the raw data
		 */
		public function dataRaw() {
			return $this->_data;
		}

		/**
		 * \brief Same as \ref data, but evaluates the content of the variable.
		 * This function must be used with care as it can be a backdoor for hackers.
		 * So, make sure you understand the risks before using this.
		 *
		 * \param name The name of the data.
		 *
		 * \return The data value.
		 */
		public function dataInclude($name) {
			$data = $this->data($name);
			/* Update the path history */
			array_push($this->_pathHistory, $this->dataPath($name));
			/* Process the data */
			$data = eval("?".">$data");
			/* Remove the path from the history */
			array_pop($this->_pathHistory);
			return $data;
		}

		/**
		 * \brief Include a file located from the path passed in argument.
		 */
		public function fileInclude($path) {
			/* Update the path history */
			array_push($this->_pathHistory, $path);
			/* Include the file */
			include($path);
			/* Remove the path from the history */
			array_pop($this->_pathHistory);
		}

		/**
		 * Return the current path
		 */
		private function _getCurrentPath() {
			return dirname(end($this->_pathHistory));
		}


		/**
		 * Generate custom headers
		 */
		private function _generateCustomHeaders() {
			/* Todo */
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
		private function _replaceTags($str, $tag_list, $required = false) {
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
		private function _fetchIndex() {
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

			return $path.IRCMS_INDEX;
		}
	}
?>
