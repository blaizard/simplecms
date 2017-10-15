<?php
	/* If the key is not defined, use the default one */
	if (!defined("IREXPLORER_KEY")) {
		define("IREXPLORER_KEY", ";a9^8K7pXyQ181q9P~VZ6Dl{Z91*+X");
	}

	class Irexplorer {

		private $_options;
		private $_data;

		/**
		 * \brief Returns a file and directory list. The location, format and other parameters
		 * can be changed with the \a options argument. This function is the main entry point.
		 * \code $file_list = irexplorer(array("path" => "/home/"));
		 * print_r($file_list); \endcode
		 *
		 * \param {array} [options] Options to customize the output of this functions. For
		 * a complete list of the available options, please refer to \see $defaults
		 *
		 *\return {array} An array containing the directory and file list.
		 */
		public function __construct($options = array()) {
			/**
			 * \brief Default options for irexplorer
			 * \type array
			 */
			$defaults = array(
				/**
				 * \brief This is the top path, the explorer cannot go above this path
				 * \type string
				 */
				"path" => "./",
				/**
				 * \brief This is the relative path from the top path directory. This path
				 * (["path"].["current"]) is the path to be listed.
				 * \type string
				 */
				"current" => "/",
				/**
				 * \brief This build a table showing the selected columns in the selected order.
				 * The column names and behavior are defined with the item \see categories
				 * \type array
				 */
				"show" => array("name", "date", "type", "size"),
				/**
				 * List of arguments to return with each row. This array contains the name of the category.
				 * \type array
				 */
				"args" => array("path", "type"),
				/**
				 * \brief This will display the files in the order of the array.
				 * For example:
				 * \li array("folder", ".*"), will first display the folders and then the rest.
				 * \li array(".*"), will display everything at once.
				 * \li array("folder", "php"), will only display the folders and the php files
				 * \type array
				 */
				"showType" => array("folder", ".*"),
				/**
				 * \brief Include hidden files.
				 * Hidden files are ".xxxx" or "@xxxx" files.
				 * \type boolean
				 */
				"showHidden" => false,
				/**
				 * \brief Columns description.
				 * Each colum must have a "title" corrseponding to the
				 * header name of the table, and a "fct", a php function
				 * to be called to generate the value of the cell.
				 */
				"categories" => array(
					/**
					 * \brief Category to display the name of the file
					 * \type categories
					 */
					"name" => array(
						"title" => "Name",
						"fct" => function($path, $options) {
							return Irexplorer::getFileName($path, $options);
						}
					),
					/**
					 * \brief Category to display the relative path of the file
					 * \type categories
					 */
					"path" => array(
						"title" => "Path",
						"fct" => function($path, $options) {
							return Irexplorer::getFilePath($path, $options);
						}
					),
					/**
					 * \brief Category to display the type of the file
					 * \type categories
					 */
					"type" => array(
						"title" => "Type",
						"fct" => function($path, $options) {
							return Irexplorer::getFileType($path, $options);
						}
					),
					/**
					 * \brief Category to display the date of the file
					 * \type categories
					 */
					"date" => array(
						"title" => "Date modified",
						"fct" => function($path, $options) {
							return Irexplorer::getFileDate($path, $options);
						}
					),
					/**
					 * \brief Category to display the size of the file
					 * \type categories
					 */
					"size" => array(
						"title" => "Size",
						"fct" => function($path, $options) {
							return Irexplorer::getFileSize($path, $options);
						}
					),
					/**
					 * \brief Category to display the thumbnail associated to the file
					 * \type categories
					 */
					"thumbnail" => array(
						"title" => "Thumbnail",
						"fct" => function($path, $options) {
							return Irexplorer::getThumbnail($path, $options);
						}
					)
				)
			);
			$this->_options = array_merge($defaults, $options);
			$this->_data = $this->data();
		}

		/**
		 * To use the imlementation of these functions you need the mcrypt module to be enabled.
		 * sudo apt-get install php5-mcrypt
		 * sudo php5enmod mcrypt
		 */
		private function encrypt($payload) {
			return base64_encode($payload);
			$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
			$iv = mcrypt_create_iv($iv_size, MCRYPT_DEV_URANDOM);
			$crypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, IREXPLORER_KEY, $payload, MCRYPT_MODE_CBC, $iv);
			$combo = $iv.$crypt;
			$garble = base64_encode($iv.$crypt);
			return $garble;
		}

		private function decrypt($garble) {
			return base64_decode($garble);
			$combo = base64_decode($garble);
			$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
			$iv = substr($combo, 0, $iv_size);
			$crypt = substr($combo, $iv_size, strlen($combo));
			$payload = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, IREXPLORER_KEY, $crypt, MCRYPT_MODE_CBC, $iv);
			return $payload;
		}

		/**
		 * \brief Get the directory list
		 * \private
		 */
		private function filelist($path) {
			/* Open the directory */
			$dir = @opendir($path);
			/* Make sure the directory has been propertly opened */
			if (!$dir) {
				throw new Exception("Cannot open the directory `".$path."'");
			}

			$file_list = array();
			while(($file = readdir($dir)) != false) {
				if ($file != "." && $file != "..") {
					if (!$this->_options["showHidden"] && preg_match("/^(\.|@).*/", $file)) {
						continue;
					}
					$file_list[] = $file;
				}
			}

			/* Close the directory */
			closedir($dir);

			/* Alphabetically order the list */
			natsort($file_list);

			/* Return the list */
			return $file_list;
		}

		/**
		 * \private
		 */
		private static function getFileType($path, $options) {
			if (is_dir($path)) {
				return "folder";
			}
			else {
				return strtolower(pathinfo($path, PATHINFO_EXTENSION));
			}
		}

		/**
		 * \private
		 */
		private static function getFilePath($path, $options) {
			$path = $options["current"].basename($path);
			return $path.((is_dir($options["path"].$path)) ? "/" : "");
		}

		/**
		 * \private
		 */
		private static function getFileDate($path, $options) {
			return filemtime($path);
		}

		/**
		 * \private
		 */
		private static function getFileName($path, $options) {
			return basename($path);
		}

		/**
		 * \private
		 */
		private static function getFileSize($path, $options) {
			/* If this is a folder, returns nothing */
			if (is_dir($path)) {
				return "";
			}
			return filesize($path);
		}

		/**
		 * \private
		 */
		private static function getThumbnail($path, $options) {
			$type = Irexplorer::getFileType($path, $options);
			// Only specific file extension can have a generated thumbnail
			if (in_array($type, array("jpg", "png", "gif"))) {
				return "thumb:".Irexplorer::encrypt($path);
			}
			return "type:".$type;
		}

		/**
		 * This is a private function used to build the list of categories and its values.
		 * \private
		 */
		private function categoryData($categroy_list, $path) {
			$data = array();
			/* loop through the categories to print */
			foreach ($categroy_list as $cat) {
				/* Check if the category exists */
				if (!isset($this->_options["categories"][$cat])) {
					throw new Exception("The category `".$cat."' does not exists, please check the spelling.");
				}
				$c = $this->_options["categories"][$cat];
				$data[$cat] = $c["fct"]($path, $this->_options);
			}
			/* Return the data */
			return $data;
		}

		/**
		 * This function will return an array containing the file infromation of
		 * a specific directory.
		 *
		 * The data returned will have the following format:
		 *      {
		 *              "columns": [
		 *                      {"id": columnID, "name": displayName},
		 *                      ...
		 *              ],
		 *              "data": [
		 *                      {
		 *                           // The data to be displayed
		 *                      	d: {
		 *                                      // A column ID and its value
		 *                                      <columnID>: <value>
		 *                                      ...
		 *                      	},
		 *                          a: {
		 *                                      <columnID>: <value>
		 *                          }
		 *                      }
		 *              ]
		 * \private
		 */
		private function data() {
			/* Clean up the path */
			$this->_options["current"] = rtrim("/".ltrim($this->_options["current"], '/'), '/')."/";
			/* Generate the current path */
			$path = rtrim(realpath($this->_options["path"].$this->_options["current"]), '/')."/";
			$file_list = $this->filelist($path);

			/* Empty data structure */
			$data = array(
				"columns" => array(),
				"data" => array()
			);

			/* Build the header */
			foreach ($this->_options["show"] as $cat) {
				/* Check if the column name exists */
				if (!isset($this->_options["categories"][$cat])) {
					throw new Exception("The category `".$cat."' does not exists, please check the spelling.");
				}
				/* Print the title of the column */
				$col = $this->_options["categories"][$cat];
				array_push($data["columns"], array(
					"id" => $cat,
					"name" => $col["title"]
				));
			}

			/* Add the upper directory if this is not the top */
			if (strlen(realpath($path)) > strlen(realpath($this->_options["path"]))) {
				array_unshift($file_list, "..");
			}

			/* Fill the table */
			foreach ($this->_options["showType"] as $show_type) {
				$regexpr_type = "_".str_replace("_", "\\_", $show_type)."_";
				/* This array will containt the remaing elements that have not been displayed */
				$remaining_file_list = array();
				/* Loop through the files to be shown */
				foreach ($file_list as $file) {

					/* Print only the type of file specified */
					if (!preg_match($regexpr_type, Irexplorer::getFileType($path.$file, $this->_options))) {
						array_push($remaining_file_list, $file);
						continue;
					}

					/* Print the columns */
					$entry = array(
						"d" => $this->categoryData($this->_options["show"], $path.$file)
					);

					/* Add arguments if any */
					if (count($this->_options["args"])) {
						$entry["a"] = $this->categoryData($this->_options["args"], $path.$file);
					}

					/* Add the row */
					array_push($data["data"], $entry);
				}
				/* Re-assign the file list with only the remaining items */
				$file_list = $remaining_file_list;
			}

			/* Return the list */
			return $data;
		}

		/**
		 * This function returns the file data
		 */
		public function getData() {
			return $this->_data;
		}

		/**
		 * Print the data
		 */
		public function format() {
			$html = "<table><thead><tr>";
			/* Build the header */
			foreach ($this->_data["columns"] as $col) {
				$html .= "<th>".$col["name"]."</th>";
			}
			$html .= "</tr></thead><tbody>";
			/* Add the content */
			foreach ($this->_data["data"]["d"] as $row) {
				$html .= "<tr>";
				foreach ($this->_options["show"] as $id) {
					$html .= "<td>".$row[$id]."</td>";
				}
				$html .= "</tr>";
			}
			$html .= "</tbody></table>";
			return $html;
		}

		/**
		 * PHP function to resize an image maintaining aspect ratio
		 * http://salman-w.blogspot.com/2008/10/resize-images-using-phpgd-library.html
		 *
		 * Creates a resized (e.g. thumbnail, small, medium, large)
		 * version of an image file and saves it as another file
		 */
		public static function imageThumbnail($path, $dst_path, $max_width = 150, $max_height = 150) {
			/* Get image information */
			list($width, $height, $type) = getimagesize($path);
			/* Open the supported image type */
			switch ($type) {
			case IMAGETYPE_GIF:
				$image = imagecreatefromgif($path);
				break;
			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg($path);
				break;
			case IMAGETYPE_PNG:
				$image = imagecreatefrompng($path);
				break;
			default:
				$image = false;
			}
			if ($image === false) {
				return false;
			}
			/* Touch the destination file, means that it can be created */
			touch($dst_path);
			$ratio = $width / $height;
			if ($width <= $max_width && $height <= $max_height) {
				$dst_width = $width;
				$dst_height = $height;
			}
			elseif ($max_width / $max_height > $ratio) {
				$dst_width = (int) ($max_height * $ratio);
				$dst_height = $max_height;
			}
			else {
				$dst_width = $max_width;
				$dst_height = (int) ($max_width / $ratio);
			}
			$dst_image = imagecreatetruecolor($dst_width, $dst_height);
			imagecopyresampled($dst_image, $image, 0, 0, 0, 0, $dst_width, $dst_height, $width, $height);
			imagejpeg($dst_image, $dst_path, 90);
			imagedestroy($image);
			imagedestroy($dst_image);

			return true;
		}

		/**
		 * This function deploys a server assuming the following modules are loaded:
		 * - Ircom
		 */
		public static function server($config = array()) {

			/* Sanity check */
			if (!isset($_GET["irexplorer"])) {
				Ircom::error("Request for Irexplorer malformed.");
			}

			switch ($_GET["irexplorer"]) {

			// Handles the fetch
			case "fetch":
				// Read the options
				$ircom = new Ircom();
				// Return the file list
				$config = array_merge_recursive($ircom->read(), $config);
				$ex = new Irexplorer($config);
				Ircom::success($ex->getData());
				break;

			/* Retrieve the thumbnail */
			case "thumbnail":
				/* Sanity check */
				if (!isset($_GET["irexplorer-thumb"]) && !isset($_GET["irexplorer-size"])) {
					Ircom::error("Missing request data.");
				}
				/* Parse the thumb argument */
				if (!preg_match('/^([a-z]+):(.+)$/', $_GET["irexplorer-thumb"], $m)) {
					Ircom::error("Malformed `irexplorer-thumb' attribute.");
				}
				switch ($m[1]) {
				case 'type':
					$path = null;
					$type = $m[2];
					break;
				case 'thumb':
					$path = Irexplorer::decrypt($m[2]);
					$type = (is_dir($path)) ? "folder" : strtolower(pathinfo($path, PATHINFO_EXTENSION));
					break;
				default:
					Ircom::error("Unkown type `".$m[1]."'.");
				}

				/* Find the file type */
				$thumbnail_path = dirname(__FILE__)."/ressources/037-file-empty.svg";
				$thumbnail_mime = "image/svg+xml";
				$thumbnail_content = null;
				switch ($type) {
				/* Folder type */
				case "folder":
					$thumbnail_path = dirname(__FILE__)."/ressources/049-folder-open.svg";
					$thumbnail_mime = "image/svg+xml";
					break;
				case "txt":
					$thumbnail_path = dirname(__FILE__)."/ressources/039-file-text2.svg";
					$thumbnail_mime = "image/svg+xml";
					break;
				case "raw":
				case "arw":
					$thumbnail_path = dirname(__FILE__)."/ressources/040-file-picture.svg";
					$thumbnail_mime = "image/svg+xml";
					break;
				case "wav":
				case "mp3":
				case "ogg":
				case "wma":
					$thumbnail_path = dirname(__FILE__)."/ressources/041-file-music.svg";
					$thumbnail_mime = "image/svg+xml";
					break;
				case "mp4":
				case "avi":
				case "wmv":
				case "mkv":
					$thumbnail_path = dirname(__FILE__)."/ressources/043-file-video.svg";
					$thumbnail_mime = "image/svg+xml";
					break;
				case "zip":
				case "rar":
				case "7z":
					$thumbnail_path = dirname(__FILE__)."/ressources/044-file-zip.svg";
					$thumbnail_mime = "image/svg+xml";
					break;
				// Image type
				case "jpg":
				case "png":
				case "gif":
					$thumbnail_mime = "image/jpeg";
					// If the cache module is present
					if (class_exists("IrcmsCache")) {
							// Create a cache with a new container
							$cache = new IrcmsCache($path.".".$_GET["irexplorer-size"].".cache", array("container" => "irexplorer-thumbs"));
							$temp_filename = basename($path).".".$_GET["irexplorer-size"].".jpg";
							if (!$cache->isValid()) {
								if (Irexplorer::imageThumbnail($path, $temp_filename, $_GET["irexplorer-size"], $_GET["irexplorer-size"]) === false) {
									Ircom::error("Error while generating the thumbnail.");
								}
								$cache->set(file_get_contents($temp_filename));
								unlink($temp_filename);
							}
							// Read the cache
							$thumbnail_content = $cache->get();
					}
					// Else use a simple cache
					else {
						// Create directory if does not exists
						$dirpath = dirname($path)."/.irexplorer-thumbs";
						if (!is_dir($dirpath)) {
							mkdir($dirpath);
						}
						$thumbnail_path = $dirpath."/".basename($path).".".$_GET["irexplorer-size"].".jpg";
						// Create a thumbnail only if it does not already exists, or if the creation date is older than the actual file
						if (!is_file($thumbnail_path) || filemtime($thumbnail_path) < filemtime($path)) {
							if (Irexplorer::imageThumbnail($path, $thumbnail_path, $_GET["irexplorer-size"], $_GET["irexplorer-size"]) === false) {
								Ircom::error("Error while generating the thumbnail.");
							}
						}
					}
					break;
				}
				// Return the image
				header("Content-type: ".$thumbnail_mime);
				echo ($thumbnail_content) ? $thumbnail_content : file_get_contents($thumbnail_path);
				die();

			// This means that the action is not supported
			default:
				Ircom::error("Unsupported action type `".$_GET["irexplorer"]."'.");
			}
		}
	}
?>
