<?php
	/**
	 * This class provides usefull functions to handle data of a page.
	 */
	class IrcmsPageContent {

		private $m_page;
		private $m_pathHistory;
		private $m_pathHistoryTemplate;
		private $m_content;
		private $m_currentVariable;

		public function __construct($page, $content) {
			$this->m_page = $page;
			$this->m_content = $content;
			$this->m_pathHistory = array();
			$this->m_pathHistoryTemplate = array();
			$this->m_currentVariable = array();
		}

		/**
		 * Check wether or not the code is from the local data file or inherited.
		 */
		public function isLocal($name = null) {
			if ($name === null) {
				$name = end($this->m_currentVariable);
				if (!$name) {
					return false;
				}
			}
			return $this->data($name) && !IrcmsPath::cmp($this->toPath($this->env("path")), dirname($this->dataPath($name)));
		}

		/**
		 * Return environement variables
		 */
		public function env($key1 = null, $key2 = null) {
			return $this->m_page->env()->get($key1, $key2);
		}

		/**
		 * Convert a path into a URL.
		 */
		public function toUrl($file, $relativePath = array(), $strict = false) {
			// First convert the path into an absolute path
			$path = $this->toPath($file, $relativePath, $strict);
			// Then convert it into a path
			try {
				return $this->m_page->env()->toUrl(($path) ? $path : $file);
			}
			catch (Exception $e) {
				$msg = "Cannot generate URL for `".$file."'\n";
				if (!$path) {
					$msg .= "    Could not convert `".$file."' into an absolute path\n";
					$msg .= "    Looked into:\n";
					foreach ($this->toPath($file, $relativePath, $strict, true) as $p) {
						$msg .= "        ".$p."\n";
					}
				}
				else {
					$msg .= "    `".$file."' got converted into `".$path."'\n";
				}
				throw new Exception($msg);
			}
		}

		/**
		 * Convert a path into an absolute path. Optionally take as argument relative paths
		 * to test with the relative path.
		 */
		public function toPath($file, $relativePath = array(), $strict = false, $returnRelativePathOnly = false) {
			$path = $this->isPath($file, $relativePath, $strict, $returnRelativePathOnly);
			if ($path === null) {
				$msg = "Invalid path `".$file."'\n";
				$msg .= "    Could not convert `".$file."' into an absolute path\n";
				$msg .= "    Looked into:\n";
				foreach ($this->isPath($file, $relativePath, $strict, true) as $p) {
					$msg .= "        ".$p."\n";
				}
				throw new Exception($msg);
			}
			return $path;
		}

		/**
		 * Check if a path is valid, if so returns it.
		 */
		public function isPath($file, $relativePath = array(), $strict = false, $returnRelativePathOnly = false) {
			// Create the realtive path
			$relativePathList = (is_array($relativePath)) ? $relativePath : array($relativePath);
			if (!$strict) {
				$relativePathList = array_merge($relativePathList, array(
					$this->m_page->getCurrentPath(),
					$this->env("fullpath", "index")
				));
			}
			return $this->m_page->env()->toPath($file, $relativePathList, $strict, $returnRelativePathOnly);
		}

		/**
		 * Add a CSS header
		 */
		public function cssHeader($file, $uniqueId = null) {
			// Try to convert it into a valid path
			$path = $this->toPath($file);
			$this->m_page->addCssHeader(($path === null) ? $file : $path, $uniqueId);
		}

		/**
		 * Add a JavaScript header
		 */
		public function jsHeader($file, $uniqueId = null) {
			// Try to convert it into a valid path
			$path = $this->toPath($file);
			$this->m_page->addJsHeader(($path === null) ? $file : $path, $uniqueId);
		}

		/**
		 * Add a CSS header from the template directory (if applicable)
		 */
		public function cssHeaderTemplate($file, $uniqueId = null) {
			// Try to convert it into a valid path
			$path = $this->toPath($file, array(
					$this->m_page->getCurrentPathTemplate(),
					$this->env("fullpath", "index")
				), true);
			$this->m_page->addCssHeader(($path === null) ? $file : $path, $uniqueId);
		}

		/**
		 * Add a CSS JavaScript from the template directory (if applicable)
		 */
		public function jsHeaderTemplate($file, $uniqueId = null) {
			// Try to convert it into a valid path
			$path = $this->toPath($file, array(
					$this->m_page->getCurrentPathTemplate(),
					$this->env("fullpath", "index")
				), true);
			$this->m_page->addJsHeader(($path === null) ? $file : $path, $uniqueId);
		}

		/**
		 * Add a custom header
		 */
		public function header($str) {
			$this->m_page->addHeader($str);
		}


		/**
		 * Disable the cache
		 */
		public function cacheDisable() {
			return $this->m_page->cache()->disable();
		}

		/**
		 * Add a dependency file to the cache.
		 * \param exists Tells if the files exists or not. If it exists, the cache
		 * will be invalidated if the file not present. Otherwise, if it does not exists,
		 * the cache will be invalidated if present.
		 * \return The full path name
		 */
		public function cacheDependency($file, $exists = true) {
			/* Try to convert it into a valid path */
			$path = $this->toPath($file);
			$this->m_page->cache()->addDependency($path, $exists);
			return $path;
		}

		/**
		 * Return the an array containing the data objects.
		 * \param onlyLast Return only the latest occcurence in the array.
		 *                 This is valid only if a data is having multipe occurences.
		 */
		private function getData($name, $onlyLast = false) {
			if (isset($this->m_content[$name])) {
				// If it uses occurence
				if (isset($this->m_content[$name]["occurences"])) {
					if ($onlyLast) {
						return array(end($this->m_content[$name]["occurences"]));
					}
					return $this->m_content[$name]["occurences"];
				}
				return array($this->m_content[$name]);
			}
			return array(array(
				"value" => "",
				"args" => array(),
				"inherit" => false,
				"type" => IrcmsContent::TYPE_STRING, 
				"path" => null,
				"template" => null
			));
		}

		/**
		 * Return the data identified by the name
		 * This value can also be assigned. Note that the assignement will only last the time of
		 * of the page generation.
		 * \param onlyLast Return only the latest occcurence. This is valid only if a data is having
		 *                 multipe occurences.
		 */
		public function data($name, $onlyLast = false) {
			$dataList = $this->getData($name, $onlyLast);
			$value = null;
			foreach ($dataList as $data) {
				switch ($data["type"]) {
				case IrcmsContent::TYPE_STRING:
					$value = (($value) ? $value : "").$data["value"];
					break;
				case IrcmsContent::TYPE_ARRAY:
					$value = array_merge(($value) ? $value : array(), $data["value"]);
					break;
				}
			}
			return $value;
		}

		/**
		 * Set the value of a data
		 */
		public function dataSet($name, $value) {
			$this->m_content[$name] = array(
				"value" => $value,
				"type" => (is_array($value)) ? IrcmsContent::TYPE_ARRAY : IrcmsContent::TYPE_STRING
			);
		}

		/**
		 * Append a value to the current value of a data
		 * It must be of the same type.
		 */
		public function dataAppend($name, $value) {
			if (isset($this->m_content[$name])) {
				$valueType = (is_array($value)) ? IrcmsContent::TYPE_ARRAY : IrcmsContent::TYPE_STRING;
				if ($this->m_content[$name]["type"] != $valueType) {
					throw new Exception("The type of the value intented to be append do not match the original type.");
				}
				switch ($valueType) {
				case IrcmsContent::TYPE_ARRAY:
					array_merge($this->m_content[$name]["value"], $value);
					break;
				case IrcmsContent::TYPE_STRING:
					$this->m_content[$name]["value"] .= $value;
					break;
				default:
					throw new Exception("Unsupported type `".$valueType."'.");
				}
			}
			else {
				$this->dataSet($name, $value);
			}
		}

		/**
		 * Return the arguments of the data identified by the name
		 */
		public function dataArgs($name, $onlyLast = false) {
			$dataList = $this->getData($name, $onlyLast);
			$args = array();
			foreach ($dataList as $data) {
				$args =  array_merge($args, $data["args"]);
			}
			return $args;
		}

		/**
		 * Return the path of the data identified by the name
		 */
		public function dataPath($name) {
			$dataList = $this->getData($name, true);
			return $dataList[0]["path"];
		}

		/**
		 * Return the path of template of the data identified by the name
		 */
		public function dataPathTemplate($name) {
			$dataList = $this->getData($name, true);
			return $dataList[0]["template"];
		}

		/**
		 * Tells whether or not the data is coming from a template.
		 */
		public function dataIsFromTemplate($name) {
			$dataList = $this->getData($name, true);
			return ($dataList[0]["path"] !== null && $dataList[0]["template"] != $dataList[0]["path"]);
		}

		/**
		 * Tells if a data is inherited from another directory or not.
		 */
		public function dataIsInherited($name) {
			$dataList = $this->getData($name, true);
			return $dataList[0]["inherit"];
		}

		/**
		 * Return the raw data
		 */
		public function dataRaw() {
			return $this->m_content;
		}

		/**
		 * Return the data names
		 */
		public function dataNames() {
			return array_keys($this->m_content);
		}

		/**
		 * Return the instance of the current page
		 */
		public function page() {
			return $this->m_page;
		}

		/**
		 * \brief Same as \ref data, but evaluates the content of the variable.
		 * This function must be used with care as it can be a backdoor for hackers.
		 * So, make sure you understand the risks before using this.
		 *
		 * \param name The name of the data.
		 */
		public function dataInclude($name, $onlyLast = false) {
			$dataList = $this->getData($name, $onlyLast);
			foreach ($dataList as $data) {
				$value = $data["value"];

				// Update the path history
				$this->m_page->pushCurrentPath($data["path"]);
				$this->m_page->pushCurrentPathTemplate($data["template"]);
				array_push($this->m_currentVariable, $name);

				// Process the data
				eval("?".">$value");

				// Remove the path from the history
				array_pop($this->m_currentVariable);
				$this->m_page->popCurrentPath();
				$this->m_page->popCurrentPathTemplate();
			}
		}

		/**
		 * \brief Include a file located from the path passed in argument.
		 * Files included with this function are automatically added to the cache dependencies.
		 */
		public function fileInclude($file, $updatePath = true) {
			// Try to convert it into a valid path and add it to the cache deps
			$path = $this->cacheDependency($file);
			// Update the path history
			if ($updatePath) {
				$this->m_page->pushCurrentPath($path);
			}
			// Include the file
			include($path);
			// Remove the path from the history
			if ($updatePath) {
				$this->m_page->popCurrentPath();
			}
		}

		/**
		 * \copydoc fileInclude
		 * Using the template directory as reference
		 */
		public function fileIncludeTemplate($file, $updatePath = true) {
			// Try to convert it into a valid path
			$path = $this->toPath($file, array(
					$this->m_page->getCurrentPathTemplate(),
					$this->env("fullpath", "index")
				), true);
			$this->fileInclude($path, $updatePath);
		}

	}
?>