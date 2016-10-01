<?php
	class Routing {
		private $_routing;
    
		public function __construct() {
			$_routing = array();
		}

		/** 
		 * Dispatch functions to handle path.
		 * It supports the following format:
		 *	/api/robots
		 *	/api/robots/search/{name}
		 *	/api/robots/{id:[0-9]+}
		 */
		public function route($method, $path, $handler) {
			$method_list = (is_array($method)) ? $method : array($method);
			/* Loop through the different methods registered */
			foreach ($method_list as $method) {
				$method = strtoupper($method);
				if (!isset($this->_routing[$method])) {
					$this->_routing[$method] = array();
				}
				array_push($this->_routing[$method], array($path, $handler));
			}
		}

		/**
		 * Hanlde the current request, with the rules previous set
		 */
		public function dispatch($uri, $method = null) {
			/* If method is unset, by default set it to the server value */
			if ($method === null) {
				$method = $_SERVER['REQUEST_METHOD'];
			}
			else {
				$method = strtoupper($method);
			}
			/* If this method is not handled */
			if (!isset($this->_routing[$method])) {
				throw new Exception('Request method not handled');
			}
			$rule_list = $this->_routing[$method];

			/* Create the master regexpr */
			$route_data = array();
			$max_vars = 0; /* This is used for future computing and will get the maximum number of variable */
			foreach ($rule_list as $rule) {
				$path = $rule[0];
				/* Build the regular expression out of the path */
				$path_list = preg_split("/{([^}:]+):?([^}]*)}/", $path, -1, PREG_SPLIT_DELIM_CAPTURE);
				$path_regexpr = $path_list[0];
				$route_data_variables = array();
				for ($i=1; $i < count($path_list); $i += 3) {
					/* Add the variable */
					array_push($route_data_variables, $path_list[$i]);
					$path_regexpr .= "(".(($path_list[$i + 1]) ? $path_list[$i + 1] : "[^/]+").")";
					if (isset($path_list[$i + 2])) {
						$path_regexpr .= $path_list[$i + 2];
					}
				}
				$max_vars = max($max_vars, count($route_data_variables));
				/* Update the root data */
				array_push($route_data, array(
					$rule[1],
					$route_data_variables,
					$path_regexpr
				));
			}

			/* Collapse the number of regular expression to be able to identify the regexpr */
			$regexpr_list = array();
			foreach ($route_data as $index => $route) {
				$path_regexpr = $route[2].str_repeat("()", $max_vars - count($route[1]) + $index);
				array_push($regexpr_list, str_replace("~", "\\~", $path_regexpr));
			}

			$regexpr = "~^(?|".join("|", $regexpr_list).")$~x";

			/* Try to match the regexpr */
			if (!preg_match($regexpr, $uri, $matches)) {
				throw new Exception('Route not found');
			}

			list($handler, $variables) = $route_data[count($matches) - $max_vars - 1];

			$vars = array();
			$i = 0;
			foreach ($variables as $name) {
				$vars[$name] = $matches[++$i];
			}

			/* Call the function with the arguments */
			$handler($vars);
		}
    }

?>
