<?php
	/**
	 * This file provides usefull functions to deal with the content.
	 * A content file is built as follow:
	 *
	 * ---ircms-xxx:{"type":"textarea"}---   <- this a flag in the file stating the name of the variable (xxx is the name of the variable)
	 *
	 * I am multipline and
	 * I am an random data <- The value of the variable
	 *
	 * ---ircms-yyy--- = I am a new value
	 *
	 * Some of the attributes are reserved and have a special meaning:
	 * "_keep"
	 * - "last" <default> Keep only the latest occurence.
	 * - "all" Keep all occurence. If this is used, the output will be an array,
	 *         which each entry corresponding to an occurence.
	 */

	/**
	 * Limit the size of the recursion to avoid overflow, for more details:
	 * http://stackoverflow.com/questions/7620910/regexp-in-preg-match-function-returning-browser-error
	 *
	 * Regular expressions need to be written the most efficient way possible to avoid recursion if possible.
	 * This article gives some guidelines to write good regexpr and avoid stack overflow:
	 * http://regexkit.sourceforge.net/Documentation/pcre/pcrestack.html
	 *
	 */
	ini_set("pcre.recursion_limit", "524"); // PHP default is 100,000, it leads to overflow issues
	header('Content-Type: text/html; charset=utf-8'); // Dealing with UTF-8 characters

	class IrcmsContent {

		private $m_env;
		private $m_cache;

		/**
		 * Data type
		 */
		const TYPE_ARRAY = 1;
		const TYPE_STRING = 2;

		/**
		 * Associate a content object with an environement variable.
		 * \param cache Optionally associate a cache instance to the content, this will update the
		 * file dependencies
		 */
		public function __construct($env, $cache = null) {
			$this->m_env = $env;
			$this->m_cache = $cache;
		}

		/**
		 * Update the cache dependencies
		 * \todo need to include the template as well
		 */
		private function _updateCacheDeps() {
			/* Get the bottom and top path */
			$current = realpath($this->m_env->get("fullpath", "current"));
			$top_length = strlen(realpath($this->m_env->get("fullpath", "data")));
			$deps = array();

			/* Loop until the current path become a parent of the top path */
			while (strlen($current) >= $top_length) {
				$file = IrcmsPath::concat($current, IRCMS_CONTENT);
				$this->m_cache->addDependency($file, file_exists($file));
				/* Update the current path */
				$current = dirname($current);
			}

			return $deps;
		}

		/**
		 * This function dumps important information about this module
		 */
		public function dump() {
			return "_getFileList() = ".var_export($this->_getFileList(), true);
		}

		/**
		 * From a path and a template directory, get the mirrored path into this template directory
		 * \return The new path created through the template directory. If the path given is already a
		 *         path going through the template, then null is returned.
		 */
		private function _getTemplatePath($path, $template) {
			// Make sure the template path is not already in the path
			if (IrcmsPath::isSubPath($template, $path)) {
				return null;
			}
			// Construct the mirrored path going through the template directory
			// For example: /this/is/the/template/ + /this/is/the/path/location -> /this/is/the/template/location
			$relative = substr($path, strlen(dirname($template)));
			if (($pos = strpos($relative, "/", 1)) === false) {
				return null;
			}
			// Return the new path and the root directory of the item mirroring the template
			// This can be then used by this same function to re-iterate the process the other way
			return array(
				IrcmsPath::concat($template, substr($relative, $pos)),
				dirname($template).substr($relative, 0, $pos)
			);
		}

		/**
		 * Add files the file list. This also take care of templates by adding the template
		 * entry to the file list as well.
		 */
		private function _fileListExtend(&$list, $path_list, $mirror = null) {
			foreach ($path_list as $path) {
				$template = $path;
				if ($mirror) {
					if ($result = Self::_getTemplatePath($path, $mirror)) {
						$path = $result[0];
					}
				}
				array_push($list, array($path, $template));
			}
		}

		/**
		 * This function will build and return the file list to be used for the content discovery
		 */
		private function _getFileList() {

			$file_list = array();
			$bottom = $this->m_env->get("fullpath", "current");
			do {
				// Look for a templates (if any)
				$template = IrcmsPath::find($bottom, $this->m_env->get("fullpath", "data"), IRCMS_TEMPLATE, IrcmsPath::FIND_DIRECTORY | IrcmsPath::FIND_EXCLUDE_BOTTOM);
				$top = ($template) ? dirname($template) : $this->m_env->get("fullpath", "data");

				// Look for all the content files in between the bottom and the top
				Self::_fileListExtend($file_list, IrcmsPath::findMulti($bottom, $top, IRCMS_CONTENT, IrcmsPath::FIND_FILE | IrcmsPath::FIND_EXCLUDE_TOP));

				// If a template directory has been found, look into it as well
				if ($template) {
					$template_path = Self::_getTemplatePath($this->m_env->get("fullpath", "current"), $template);
					if ($template_path) {
						Self::_fileListExtend($file_list, IrcmsPath::findMulti($template_path[0], $template, IRCMS_CONTENT), $template_path[1]);
					}
					$bottom = $top;
				}
			} while ($template);

			// Include the top directory that were omitted
			Self::_fileListExtend($file_list, IrcmsPath::findMulti($top, $top, IRCMS_CONTENT));
			return $file_list;
		}

		/**
		 * Read and return the content variable.
		 *
		 * \return The content
		 */
		public function read() {

			// Update the file dependencies of the cache
			if ($this->m_cache) {
				$this->_updateCacheDeps();
			}

			// Get the content file list
			$file_list = $this->_getFileList();

			// Initializes the data, empty at first
			$data = array();

			foreach ($file_list as $file) {
				// Read and decode the file contents
				$cur_data = IrcmsContent::readFile($file[1]);
				// If the current data are data inherited from upper directories or not
				$isInherit = ($file[1] == $this->m_env->get("fullpath", "content")) ? false : true;
				// Extends the array
				foreach ($cur_data as $key => $value) {
					// Ignore if this value is already set, the priority goes to the firstly discovered files
					if (isset($data[$key]) && !$data[$key]["continue"]) {
						continue;
					}
					// Identify the current keep strategy
					$keep = (isset($value["args"]["_keep"])) ? $value["args"]["_keep"] : "last";
					// If this element should use occurence or not
					$useOccurences = ($keep == "all" || (isset($data[$key]) && $data[$key]["continue"])) ? true : false;
					// Set wether or not this value is inherited from a previous content
					$value["inherit"] = $isInherit;
					// Mark the path of from where this value come from
					$value["path"] = $file[0];
					// Mark the template path from where this value come from
					$value["template"] = $file[1];
					// Mark the template path from where this value come from
					$value["type"] = (is_array($value["value"])) ? Self::TYPE_ARRAY : Self::TYPE_STRING;
					// If the value use occurence
					if ($useOccurences) {
						if (isset($data[$key]) && !isset($data[$key]["occurences"])) {
							throw new Exception("This case should never happen, if the data is set, it should be an occurences-type data.");
						}
						else if (!isset($data[$key])) {
							$data[$key] = array(
								"occurences" => array()
							);
						}
						// Push at the begining of the array, making the first entry discovered the latest one
						array_unshift($data[$key]["occurences"], $value);
					}
					else {
						// Add the value to the list
						$data[$key] = $value;
					}
					// Defines if the exploration should continue for this value or stop
					$data[$key]["continue"] = ($keep == "all") ? true : false;
				}
			}

			return $data;
		}

		/**
		 * This function will read and decode a content file.
		 *
		 * \param filename The path of the file containing the data to be read.
		 *
		 * \return An array containing the data.
		 * The array keys are the varaible names and the values, the variable values.
		 * The variable name tolerates only the following characters: [a-z_].
		 */
		public static function readFile($filename) {
			/* Read the content of the file */
			$content = file_get_contents($filename);
			if ($content === false) {
				throw new Exception("Error while reading `".$filename."'.");
			}

			/* Split the content to dissociate the different variables */
			$split = preg_split("/\---ircms\s*-\s*([a-z_]+)(?:#?([a-z]*))\s*(?::?(.*?)(?=---))---\s*=?/i", $content, -1, PREG_SPLIT_DELIM_CAPTURE);

			/* Ignore the first element, this is what comes before any matching */
			array_shift($split);

			/* Build the array */
			$data = array();
			for ($i=0; $i<count($split); $i+=4) {
				$key = strtolower($split[$i]);
				$type = $split[$i + 1];
				$args = $split[$i + 2];
				$value = $split[$i + 3];
				/* Clean-up the data (remove empty lines and spaces at the beginning and at the end) */
				$value = trim($value);

				/* format the value (remove special patterns) */
				$value = str_replace("\\]", "]", $value);

				/* Update the $value type */
				switch ($type) {
				case "json":
					$value = json_decode($value, true);
					break;
				case "string":
				default:
				}

				/* Store the data into the array */
				$data[$key] = array(
					"value" => $value,
					"args" => ($args) ? json_decode($args, true) : array()
				);
			}

			return $data;
		}

		/**
		 * This function will write the current content to a file. Note that all
		 * previous content of the file will be replaced by the new content.
		 *
		 * \param filename The path of the file where the data should be written
		 * \param data The data to be written
		 */
		public static function writeFile($filename, $data_list) {
			$content = "";
			/* This loop will create the file data to be written */
			foreach ($data_list as $name => $data) {
				/* Extract the content and argument parts */
				$value = (isset($data["value"])) ? $data["value"] : "";
				$args = (isset($data["args"])) ? $data["args"] : array();

				/* format the value (remove special patterns) */
				$value = str_replace("]", "\\]", $value);

				/* Look for the type of data */
				if (is_string($value)) {
				        $type = "";
				}
				/* Otherwise this must be a json type */
				else {
					$type = "json";
					$value = json_encode($value);
				}
				$content .= "\n---ircms-".$name.(($type)?"#".$type:"").":".json_encode($data["args"])."---\n";
				$content .= $value."\n";
			}
			/* Write the file content to the file */
			if (file_put_contents($filename, $content) === false) {
				throw new Exception("Error while writing `".$filename."'.");
			}
		}
	}
?>
