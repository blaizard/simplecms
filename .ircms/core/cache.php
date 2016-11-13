<?php
	/**
	 * This file takes care of all the caching system.
	 * The cache is located here: IRCMS_CACHE
	 * Note that if IRCMS_CACHE is equal to false, that means the cache
	 * is disabled.
	 * This file is standalone, i.e. it does not depend on any other files.
	 */
	class IrcmsCache {

		/**
		 * Activate debug
		 */
		const IRCMS_CACHE_DEBUG = false;

		private $m_id;
		private $m_path;
		private $m_deps;
		private $m_options;
		private $m_config;
		private static $m_default = array(
			/**
			 * The root path of the cache directory
			 */
			"path" => "/.cache",
			/**
			 * The container assigned for this cache set
			 */
			"container" => "default",
			/**
			 * The cache default configuration file name
			 */
			"config" => ".cache",
			/**
			 * The time in seconds after which an item from the cache is declared invalid.
			 * If set to 0 or null, it will be ignored
			 */
			"invalid_time" => 86400,
			/**
			 * This will run the clean function after every interval
			 */
			"clean_interval" => 600,
			/**
			 * The maximum number of files to check for garbage collection at a time. If set to 0,
			 * it will loop through everything.
			 */
			"clean_chunk" => 10,
			/**
			 * To enable or disable the cache
			 */
			"enable" => true
		);

		public function __construct($file, $options = array()) {
			$this->m_options = array_merge(IrcmsCache::$m_default, $options);

			// List of file dependecies for this cache, i.e if one of these files is altered, the cache should be invalidated
			$this->m_deps = array();

			// Store the cache id
			$this->m_id = $file;

			// Do the following only if the cache is enabled
			if ($this->m_options["enable"]) {
				// Make sure the cache directory exists
				if (!file_exists($this->m_options["path"])) {
					mkdir($this->m_options["path"]);
				}
				// Cleanup the path
				$this->m_options["path"] = realpath($this->m_options["path"]);

				// Generate the full path of the file id if relevant
				$this->m_path = $this->m_options["path"].DIRECTORY_SEPARATOR.$this->m_options["container"].DIRECTORY_SEPARATOR.ltrim($this->m_id, DIRECTORY_SEPARATOR);

				// Build the config file full path
				$this->m_config = $this->m_options["path"].DIRECTORY_SEPARATOR.ltrim($this->m_options["config"], DIRECTORY_SEPARATOR);

				// Clean the cache if needed
				if (!file_exists($this->m_config) || time() - filemtime($this->m_config) > $this->m_options["clean_interval"]) {
					$this->clean($this->m_options["clean_chunk"]);
				}
			}
		}

		/**
		 * Set the default cache configuration
		 */
		public static function setDefault($options) {
			IrcmsCache::$m_default = array_merge(IrcmsCache::$m_default, $options);
		}

		/**
		 * Create a cache container. Each container contains a set of cached files
		 * for a specific use. A container is simply a way to partition different
		 * use fo the cache.
		 */
		public function getContainer() {
			return $this->m_options["container"];
		}

		/**
		 * This function is a garbage collector of the cache and will clean it.
		 * It will go through each files and make sure they are still valid, if not, it will remove them.
		 */
		public function clean($chunk = -1) {

			/* Open and lock the file, to ensure that only 1 instance is running at a time */
			$fp = @fopen($this->m_config, "r+");
			/* If the file does not exists, create it */
			if (!$fp) {
				$fp = fopen($this->m_config, "w");
			}
			/* Lock the file, if the file is already locked just return */
			if (!flock($fp, LOCK_EX | LOCK_NB)) {
				return false;
			}
			/* Read and decode the content */
			$config = array();
			$filesize = filesize($this->m_config);
			if ($chunk > 0 && $filesize && (($content = fread($fp, $filesize)) !== false)) {
				$config = json_decode($content, true);
				$config = (!is_array($config)) ? array() : $config;
			}

			$config = array_merge(array(
				"path" => null,
				"_size" => 0,
				"_nb" => 0
			), $config);

			/* Collect the garbage */
			$config = $this->_garbageCollect($config, $chunk);

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

			if (Self::IRCMS_CACHE_DEBUG) {
				echo "<!-- Cache starts at `".$config["path"]."' (chunk=".$chunk.") //-->\n";
			}

			/* Initialize the current path
			 * Separate the directory name from the filename and make sure they are valid, if not find the top one
			 */
			$top_length = strlen($this->m_options["path"]);
			$dirpath = $this->m_options["path"].DIRECTORY_SEPARATOR.$config["path"];
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

			if (Self::IRCMS_CACHE_DEBUG) {
				echo "<!-- Cache variables (dirpath:".$dirpath.";filename:".$filename." //-->\n";
			}

			/* Statistis */
			$stats = array();

			/* Go through the files first */
			$output = $this->_garbageCollectRecursive($chunk, $stats, $dirpath, $filename);

			/* If this was not the top directory, loop through the upper ones */
			while (!$output && strlen($dirpath) > $top_length) {
				$filename = basename($dirpath);
				$dirpath = dirname($dirpath);
				/* Loop through the rest */
				$output = $this->_garbageCollectRecursive($chunk, $stats, $dirpath, $filename);
			}

			/* Update the cache configuration file.
			 * Make the path relative to the other one, this is doable since both are cleaned by realpath
			 */
			$config["path"] = ($output) ? substr($output, $top_length + 1) : null;

			if (Self::IRCMS_CACHE_DEBUG) {
				echo "<!-- Cache ends at `".$config["path"]."' //-->\n";
			}

			/* Update the statistical records */
			$config["_nb"] += count($stats);
			foreach ($stats as $file) {
				$config["_size"] += filesize($file);
			}
			/* This means the loop is completed, all files have been discovered */
			if ($config["path"] === null) {
				$config["nb"] = $config["_nb"];
				$config["size"] = $config["_size"];
				$config["_nb"] = $config["_size"] = 0;
			}

			return $config;
		}

		/**
		 * Recursive function that will go through the directories and files and check their validity
		 * \param nb_files The number of files to process
		 * \param stats The of files that have been discovered and that kept into the cache.
		 * This list can be used later for statiscical analysis.
		 */
		private function _garbageCollectRecursive(&$nb_files, &$stats, $dirname, $start_filename = null) {
			/* Open the directory */
			$dir = @opendir($dirname);
			/* Make sure the directory has been propertly opened */
			if (!$dir) {
				return exception_throw("Cannot open the directory `".$dirname."'");
			}

			/* Loop through the files */
			$start = ($start_filename) ? false : true;
			while (($file = readdir($dir)) != false) {
				if ($file != "." && $file != ".." && $file != $this->m_options["config"]) {
					if (!$start && $start_filename == $file) {
						$start = true;
					}
					/* If start is set, then it will go though the files from there, note that we bypas intentionaly the first file */
					else if ($start) {
						$path = $dirname.DIRECTORY_SEPARATOR.$file;
						if (Self::IRCMS_CACHE_DEBUG) {
							echo "<!-- Cache file: `".$path."' (time=".(time() - filemtime($path))."s) ".((is_dir($path)) ? "FOLDER" : "FILE")." //-->\n";
						}
						/* If this is a directory, jumps into it and re-iterate */
						if (is_dir($path)) {
							$start_nb_files = $nb_files;
							$output = $this->_garbageCollectRecursive($nb_files, $stats, $path);
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
							if ($this->m_options["invalid_time"] && (time() - filemtime($path) > $this->m_options["invalid_time"])) {
								/* Deletes this entry */
								unlink($path);
							}
							/* If this file is kept, update the start filename variable, this will be the next
							 * starting point if this function returns
							 */
							else {
								$start_filename = $file;
								/* Add this file to the list */
								array_push($stats, $path);
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
			$this->m_options["enable"] = false;
		}

		private static function _addDependency(&$deps, $path, $exists = true) {
			/* Check if the element already exists */
			foreach ($deps as $dep) {
				if ($dep[0] == $path) {
					if ($dep[1] != $exists) {
						throw new Exception("The same dependency `".$path."' is added twice with conflicting options.");
					}
					return;
				}
			}
			/* If not add it */
			array_push($deps, array($path, $exists));
		}

		/**
		 * Add a file dependency to this cache
		 * \param exists will invalid the cache if the file does not exists. If false,
		 * it will invalid the cache if the file exist.
		 */
		public function addDependency($path, $exists = true) {
			IrcmsCache::_addDependency($this->m_deps, $path, $exists);
		}

		/**
		 * Create an item and store it in the cache.
		 * Cahe format uses the first line to store the file dependencies and he rest for the data
		 */
		public function set($data) {
			if (!$this->m_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			/* Bypass if the cache is not enabled */
			if (!$this->m_options["enable"]) {
				return;
			}
			/* Create the path if it does not exists already */
			@mkdir(dirname($this->m_path), 0777, true);
			/* Update the content */
			$content = json_encode($this->m_deps)."\n".$data;
			/* Create the asset */
			file_put_contents($this->m_path, $content);
		}

		/**
		 * Return the cache ID
		 */
		public function getId() {
			if (!$this->m_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			return $this->m_id;
		}

		/**
		 * Retrieve the cache
		 */
		public function get() {
			if (!$this->m_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			/* Touch the file only if the cache is enabled */
			if ($this->m_options["enable"]) {
				/* Touch the file to update its timestamp */
				touch($this->m_path);
			}
			/* Read the content of the file */
			$content = file_get_contents($this->m_path);
			/* Discard the first line (which contains the dependencies) */
			return substr($content, strpos($content, "\n") + 1);
		}

		/**
		 * Return the last modification time of the cache
		 */
		public function timestamp() {
			if (!$this->m_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			return filemtime($this->m_path);
		}

		/**
		 * Returns true of false if the cache exists
		 */
		public function isCached() {
			if (!$this->m_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			return file_exists($this->m_path);
		}

		/**
		 * Check wether a cache entry is valid or not
		 */
		public function isValid() {
			if (!$this->m_id) {
				throw new Exception("This cache is not linked to a file, please check the configuration.");
			}
			/* Bypass if the cache is not enabled */
			if (!$this->m_options["enable"]) {
				if (Self::IRCMS_CACHE_DEBUG) {
					echo "<!-- Cache entry `".$this->m_id."' is invalid because the cache is disabled. //-->";
				}
				return false;
			}
			/* If the entry does not exists */
			if (!$this->isCached()) {
				if (Self::IRCMS_CACHE_DEBUG) {
					echo "<!-- Cache entry `".$this->m_id."' is invalid because the entry is not in the cache. //-->";
				}
				return false;
			}
			/* Get the file dependency list */
			$file = fopen($this->m_path, "r");
			$deps_list = json_decode(fgets($file));
			fclose($file);
			/* If the dependency list is altered, raise an error */
			if (!is_array($deps_list)) {
				if (Self::IRCMS_CACHE_DEBUG) {
					echo "<!-- Cache entry `".$this->m_id."' is corrupted. //-->";
				}
				return false;
			}
			/* Merge the dependencies with the current ones */
			foreach ($this->m_deps as $elt) {
				IrcmsCache::_addDependency($deps_list, $elt[0], $elt[1]);
			}
			/* Caclulate the timestamp of the cache */
			$cache_time = $this->timestamp();
			/* Calculate the max dependency timestamp */
			foreach ($deps_list as $elt) {
				$file = $elt[0];
				$exists = $elt[1];
				/* If the file does not exists but it should */
				if ($exists && !file_exists($file)) {
					if (Self::IRCMS_CACHE_DEBUG) {
						echo "<!-- Cache entry `".$this->m_id."' is invalid because the dependency `".$file."' does not exists. //-->";
					}
					return false;
				}
				/* If the file exists but it should not */
				if (!$exists && file_exists($file)) {
					if (Self::IRCMS_CACHE_DEBUG) {
						echo "<!-- Cache entry `".$this->m_id."' is invalid because the dependency `".$file."' exists. //-->";
					}
					return false;
				}
				/* If the file is newer than the cache */
				if ($exists && filemtime($file) > $cache_time) {
					if (Self::IRCMS_CACHE_DEBUG) {
						echo "<!-- Cache entry `".$this->m_id."' is invalid because the dependency `".$file."' is newer than the cache. //-->";
					}
					return false;
				}
			}
			return true;
		}
	}
?>
