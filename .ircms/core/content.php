<?php
	/**
	 * This file provides usefull functions to deal with the content.
	 * A content file is built as follow:
	 *
	 * [ircms-xxx]   <- this a flag in the file stating the name of the variable (xxx is the name of the variable)
	 *
	 * I am multipline and
	 * I am an random data <- The value of the variable
	 *
	 * [ircms-yyy] = I am a new value
	 *
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

		private $_env;

		/**
		 * Associate a content object with an environement variable
		 */
		public function __construct($env) {
			$this->_env = $env;
		}

		/**
		 * Read and return the content variable.
		 *
		 * \return The content
		 */
		public function read() {
			/* Get the content file list */
			$file_list = IrcmsPath::findMulti($this->_env->get("fullpath", "current"), $this->_env->get("fullpath", "data"), IRCMS_CONTENT);

			/* Initializes the data, empty at first */
			$data = array();

			foreach ($file_list as $file) {
				/* Read and decode the file contents */
				$cur_data = IrcmsContent::readFile($file);
				/* If the current data are data inherited from upper directories or not */
				$isInherit = ($file == $this->_env->get("fullpath", "content")) ? false : true;
				/* Extends the array */
				foreach ($cur_data as $key => $value) {
					/* Ignore if this value is already set, the priority goes to the firstly discovered files */
					if (isset($data[$key])) {
						continue;
					}
					/* Set wether or not this value is inherited from a previous content */
					$value["inherit"] = $isInherit;
					/* Mark the path of from where thsi value come from */
					$value["path"] = $file;
					/* Add the value to the list */
					$data[$key] = $value;
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
					"args" => ($args) ? $args : "{}"
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
