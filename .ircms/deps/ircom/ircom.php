<?php
	class Ircom {

		private $_options;

		/**
		 * Initialize the communication module
		 */
		public function __construct($options = array()) {
			$this->_options = array_merge(Ircom::$defaults, $options);
		}

		/**
		 * \brief Default options for the ircom module
		 * \type array
		 */
		public static $defaults = array(
			/**
			 * \brief The default method used to communicate with the client
			 * \type string
			 */
			"method" => "post"
		);

		/**
		 * \brief This function reads the data previously by the ircom module.
		 *
		 * \param {array} [options] Specific options to pass to the function, see \see $ircom_defaults for more details.
		 *
		 * \return {mixed} The data.
		 */
		public function read() {
			switch ($this->_options["method"]) {
			case "post":
				/* Make sure the POST data are set */
				if (!isset($_POST) || !isset($_POST["ircom"])) {
					throw new Exception("The attribute `ircom' is missing.");
				}
				/* All the data are stored into the POST data */
				$data = json_decode($_POST["ircom"], true);
				/* If an exception has been catched */
				if ($data === null) {
					throw new Exception("Error while decoding the JSON data.");
				}
				/* The result must be an array */
				if (!is_array($data)) {
					throw new Exception("The result must be an array.");
				}
				break;
			default:
				throw new Exception("The method `".$this->_options["method"]."' is not supported.");
			}

			/* Return the data */
			return $data;
		}

		/**
		 * \brief Acknowledge a successul transfer.
		 *
		 * \param {mixed} [data] Optional data to transfer back.
		 */
		public static function success($data = null) {
			/* Set the output into "$ob_output" */
			header("HTTP/1.0 200 Success");
			echo json_encode($data);
			die();
		}

		/**
		 * \brief Negative acknowledgement to a ircom transaction.
		 * Once this function is called, the process of the php
		 * file stops.
		 *
		 * \param {mixed} [message] The error message to be returned.
		 */
		public static function error($message = "Unknown error") {
			header("HTTP/1.0 403 Forbidden");
			echo json_encode($message);
			die();
		}
	}
?>
