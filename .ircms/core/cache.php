<?php
	if (!defined("IRCMS_CACHE_DEBUG")) {
		define("IRCMS_CACHE_DEBUG", false);
	}

	/**
	 * This file takes care of all the caching system.
	 * The cache is located here: IRCMS_CACHE
	 * Note that if IRCMS_CACHE is equal to false, that means the cache
	 * is disabled.
	 * This file is standalone, i.e. it does not depend on any other files.
	 */
	class IrcmsCache {

		private $_id;
		private $_path;
		private $_deps;
		private $_options;

		public function __construct($options, $file = null) {

			$this->_options = array_merge(array(
				/**
				 * The root path of the cache directory
				 */
				"path" => dirname(__FILE__)."/.cache",
				/**
				 * The cache default configuration file name
				 */
				"config" => ".cache.part",
				/**
				 * The time in seconds after which an item from the cache is declared invalid.
				 * If set to 0 or null, it will be ignored
				 */
				"invalid_time" => 3600 * 24,
				/**
				 * The maximum number of files to check for garbage collection at a time. If set to 0,
				 * it will loop through everything.
				 */
				"chunk" => 10,
				/**
				 * To enable or disable the cache
				 */
				"enable" => true
			), $options);

			/* List of file dependecies for this cache, i.e if one of these files is altered, the cache should be invalidated */
			$this->_deps = array();

			/* Make sure the cache directory exists */
			if (!file_exists($this->_options["path"])) {
				mkdir($this->_options["path"]);
			}
			/* Cleanup the path */
			$this->_options["path"] = realpath($this->_options["path"]);

			/* Store the cache id */
			$this->_id = $file;
			if ($file) {
				/* Generate the full path of the file id if relevant */
				$this->_path = $this->_options["path"].DIRECTORY_SEPARATOR.ltrim($this->_id, DIRECTORY_SEPARATOR);
			}

			/* Clean the cache if needed */
			$this->clean();
		}

		/**
		 * This function is a garbage collector of the cache and will clean it.
		 * It will go through each files and make sure they are still valid, if not, it will remove them.
		 */
		public function clean() {

			/* Generate the cache full path */
			$cache_file = $this->_options["path"].DIRECTORY_SEPARATOR.ltrim($this->_options["config"], DIRECTORY_SEPARATOR);

			/* Open and lock the file, to ensure that only 1 instance is running at a time */
			$fp = @fopen($cache_file, "r+");
			/* If the file does not exists, create it */
			if (!$fp) {
				$fp = fopen($cache_file, "w");
			}
			/* Lock the file, if the file is already locked just return */
			if (!flock($fp, LOCK_EX | LOCK_NB)) {
				return false;
			}
			/* Read and decode the content */
			$config = array();
			$filesize = filesize($cache_file);
			if ($filesize && (($content = fread($fp, $filesize)) !== false)) {
				$config = json_decode($content, true);
				$config = (!is_array($config)) ? array() : $config;
			}

			$config = array_merge(array(
				"path" => null
			), $config);

			/* Collect the garbage */
			$config = $this->_garbageCollect($config, $this->_options["chunk"]);

			/* Write the new configuration file */
			ftruncate($fp, 0);
			rewind($fp);
			fwrite($fp, json_encode($config));

			/* Release the lock and close the file */
			flock($fp, LOCK_UN);
			fclose($fp);

			return true;
		}

		private function _garbageCollect($config, $chunk) {

			if (IRCMS_CACHE_DEBUG) {
				echo "<!-- Cache starts at `".$config["path"]."' (chunk=".$this->_options["chunk"].") //-->\n";
			}

			/* Initialize the current path
			 * Separate the directory name from the filename and make sure they are valid, if not find the top one
			 */
			$top_length = strlen($this->_options["path"]);
			$dirpath = $this->_options["path"].DIRECTORY_SEPARATOR.$config["path"];
			$filename = (file_exists($dirpath) && is_file($dirpath)) ? basename($dirpath) : null;

			/* If it does not exists, go to the top directory */
			while (!is_dir($dirpath)) {
				/* If dirpath goes above the cache root path, it means that there is no cache present */
				if (strlen($dirpath) <= $top_length) {
					return null;
				}
				$dirpath = dirname($dirpath);
			}
			/* At this point we know that the directory exists, hence clean it up and make sure it is a child of the top path */
			$dirpath = realpath($dirpath);
			if (strlen($dirpath) < $top_length) {
				return null;
			}

			if (IRCMS_CACHE_DEBUG) {
				echo "<!-- Cache variables (dirpath:".$dirpath.";filename:".$filename." //-->\n";
			}

			/* Go through the files first */
			$output = $this->_garbageCollectRecursive($chunk, $dirpath, $filename);

			/* If this was not the top directory, loop through the upper ones */
			while (!$output && strlen($dirpath) > $top_length) {
				$filename = basename($dirpath);
				$dirpath = dirname($dirpath);
				/* Loop through the rest */
				$output = $this->_garbageCollectRecursive($chunk, $dirpath, $filename);
			}

			/* Update the cache configuration file.
			 * Make the path relative to the other one, this is doable since both are cleaned by realpath
			 */
			$config["path"] = ($output) ? substr($output, $top_length + 1) : null;

			if (IRCMS_CACHE_DEBUG) {
				echo "<!-- Cache ends at `".$config["path"]."' //-->\n";
			}

			return $config;
		}

		/**
		 * Recursive function that will go through the directories and files and check their validity
		 * \param nb_files The number of files to process
		 */
		private function _garbageCollectRecursive(&$nb_files, $dirname, $start_filename = null) {
			/* Open the directory */
			$dir = @opendir($dirname);
			/* Make sure the directory has been propertly opened */
			if (!$dir) {
				return exception_throw("Cannot open the directory `".$dirname."'");
			}

			/* Loop through the files */
			$start = ($start_filename) ? false : true;
			while (($file = readdir($dir)) != false) {
				if ($file != "." && $file != ".." && $file != $this->_options["config"]) {
					if (!$start && $start_filename == $file) {
						$start = true;
					}
					/* If start is set, then it will go though the files from there, note that we bypas intentionaly the first file */
					else if ($start) {
						$path = $dirname.DIRECTORY_SEPARATOR.$file;
						if (IRCMS_CACHE_DEBUG) {
							echo "<!-- Cache file: `".$path."' (time=".(time() - filemtime($path))."s) ".((is_dir($path)) ? "FOLDER" : "FILE")." //-->\n";
						}
						/* If this is a directory, jumps into it and re-iterate */
						if (is_dir($path)) {
							$start_nb_files = $nb_files;
							$output = $this->_garbageCollectRecursive($nb_files, $path);
							/* If the output is set, it means that the file discovery is not complete */
							if ($output) {
								closedir($dir);
								return $output;
							}
							/* If no files have been discovered, then delete this directory */
							if ($start_nb_files == $nb_files) {
								rmdir($path);
							}
						}
						else {
							/* Decrease the file counter only for files */
							$nb_files--;
							/* If file is too old, delete it */
							if ($this->_options["invalid_time"] && (time() - filemtime($path) > $this->_options["invalid_time"])) {
								/* Deletes this entry */
								unlink($path);
							}
							/* If this file is kept, update the start filename variable, this will be the next
							 * starting point if this function returns
							 */
							else {
								$start_filename = $file;
							}
							/* If the loop has been through enough files, returns */
							if ($nb_files == 0) {
								closedir($dir);
								return $dirname.DIRECTORY_SEPARATOR.$start_filename;
							}
						}
					}
				}
			}

			/* Close the dir previously opened */
			closedir($dir);
			return null;
		}

		/**
		 * Disable the cache
		 */
		public function disable() {
			$this->_options["enable"] = false;
		}

		/**
		 * Add a file dependency to this cache
		 * \param exists will invalid the cache if the file does not exists. If false,
		 * it will invalid the cache if the file exist.
		 */
		public function addDependency($path, $exists = true) {
			$elt = array($path, $exists);
			/* Check if the element already exists */
			if (!in_array($elt, $this->_deps)) {
				array_push($this->_deps, $elt);
			}
		}

		/**
		 * Create an item and store it in the cache.
		 * Cahe format uses the first line to store the file dependencies and he rest for the data
		 */
		public function set($data) {
			if (!$this->_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			/* Bypass if the cache is not enabled */
			if (!$this->_options["enable"]) {
				return;
			}
			/* Create the path if it does not exists already */
			@mkdir(dirname($this->_path), 0777, true);
			/* Update the content */
			$content = json_encode($this->_deps)."\n".$data;
			/* Create the asset */
			file_put_contents($this->_path, $content);
		}

		/**
		 * Return the cache ID
		 */
		public function getId() {
			if (!$this->_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			return $this->_id;
		}

		/**
		 * Retrieve the cache
		 */
		public function get() {
			if (!$this->_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			/* Touch the file only if the cache is enabled */
			if ($this->_options["enable"]) {
				/* Touch the file to update its timestamp */
				touch($this->_path);
			}
			/* Read the content of the file */
			$content = file_get_contents($this->_path);
			/* Discard the first line (which contains the dependencies) */
			return substr($content, strpos($content, "\n") + 1);
		}

		/**
		 * Return the last modification time of the cache
		 */
		public function timestamp() {
			if (!$this->_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			return filemtime($this->_path);
		}

		/**
		 * Returns true of false if the cache exists
		 */
		public function isCached() {
			if (!$this->_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			return file_exists($this->_path);
		}

		/**
		 * Check wether a cache entry is valid or not
		 */
		public function isValid() {
			if (!$this->_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			/* Bypass if the cache is not enabled */
			if (!$this->_options["enable"]) {
				return false;
			}
			/* If the entry does not exists */
			if (!$this->isCached()) {
				return false;
			}
			/* Get the file dependency list */
			$file = fopen($this->_path, "r");
			$deps_list = json_decode(fgets($file));
			fclose($file);
			/* Merge the dependencies with the current ones */
			foreach ($deps_list as $elt) {
				$this->addDependency($elt[0], $elt[1]);
			}
			/* Caclulate the timestamp of the cache */
			$cache_time = $this->timestamp();
			/* Calculate the max dependency timestamp */
			foreach ($this->_deps as $elt) {
				$file = $elt[0];
				$exists = $elt[1];
				/* If the file does not exists but it should */
				if ($exists && !file_exists($file)) return false;
				/* If the file exists but it should not */
				if (!$exists && file_exists($file)) return false;
				/* If the file is newer than the cache */
				if ($exists && filemtime($file) > $cache_time) return false;
			}
			return true;
		}
	}
?>
