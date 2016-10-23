<?php
	class IrcmsHTML {
		const SEPARATOR = ",\s+>~";
		const CHARSET_ATTRIBUTE = "a-z0-9\-_";

		const VALUE_EQUALS = 1;
		const VALUE_CONTAINS = 2;
		const VALUE_CONTAINS_WORD = 3;
		const VALUE_BEGINS = 4;
		const VALUE_ENDS = 5;

		const BODY_START = "<div id=\"IrcmsHTML-DOMDocument-Object-Body\">";
		const BODY_END = "</div>";

		private $m_doc;
		private $m_xpath;
		private $m_selection;

		/**
		 * Loads a HTML 
		 */
		public function __construct($html) {
			$this->m_doc = new DOMDocument();
			$this->m_doc->loadHTML(Self::BODY_START.$html.Self::BODY_END);
			$this->m_xpath = new DomXPath($this->m_doc);
			$this->m_selection = array();
		}

		/**
		 * \brief Helper function to generate a nested pattern
		 */
		private static function patternNested($opening_char, $closing_char, $id = null) {
			static $id = 0;
			$id++;
			return "(?<patternNested".$id.">".$opening_char."(?:(?".">[^".$opening_char.$closing_char."]*+)|(?P>patternNested".$id."))*".$closing_char.")";
		}

		private static function nodeToDocument($nodes) {
			$new_doc = new DOMDocument();
			foreach ($nodes as $node) {
				$new_doc->appendChild($new_doc->importNode($node, true));
			}
		}

		/**
		 * Find all the nodes that matches with the tag name
		 */
		private function searchByTagName($selection, $name) {
			$nodes = array();
			foreach ($selection as $node) {
				$result = $node->getElementsByTagName($name);
				foreach ($result as $n) {
					array_push($nodes, $n);
				}
			}
			return $nodes;
		}

		/**
		 * Find all the nodes that have a specific attribute.
		 */
		private function searchByAttribute($selection, $attr, $value = null, $option = Self::VALUE_EQUALS) {
			$nodes = array();
			foreach ($selection as $node) {
				if ($value === null || $value == "*") {
					$result = $this->m_xpath->query("//*[@".$attr."]", $node);
				}
				else if ($option == Self::VALUE_EQUALS) {
					$result = $this->m_xpath->query("//*[@".$attr."='".$value."']", $node);
				}
				else if ($option == Self::VALUE_CONTAINS) {
					$result = $this->m_xpath->query("//*[contains(@".$attr.", '".$value."')]", $node);
				}
				else if ($option == Self::VALUE_CONTAINS_WORD) {
					$result = $this->m_xpath->query("//*[contains(concat(' ', normalize-space(@".$attr."), ' '), ' ".$value." ')]", $node);
				}
				else {
					throw new Exception("Unkown option (".$option.")");
				}
				foreach ($result as $n) {
					array_push($nodes, $n);
				}
			}
			return $nodes;
		}

		/**
		 * Filter only the nodes that have a specific attribute.
		 */
		private function filterByAttribute($selection, $attr, $value = null, $option = Self::VALUE_EQUALS) {
			$nodes = array();
			foreach ($selection as $node) {
				$element = new IrcmsHTMLElement($node);
				if ($value === null || $value == "*") {
					if ($element->hasAttr($attr)) {
						array_push($nodes, $node);
					}
				}
				else if ($option == Self::VALUE_EQUALS) {
					if ($element->hasAttr($attr) && $element->attr($attr) == trim($value)) {
						array_push($nodes, $node);
					}
				}
				else if ($option == Self::VALUE_BEGINS) {
					if ($element->hasAttr($attr) && strpos($element->attr($attr), trim($value)) === 0) {
						array_push($nodes, $node);
					}
				}
				else if ($option == Self::VALUE_ENDS) {
					if ($element->hasAttr($attr) && substr($element->attr($attr), -strlen(trim($value))) == trim($value)) {
						array_push($nodes, $node);
					}
				}
				else if ($option == Self::VALUE_CONTAINS) {
					if ($element->hasAttr($attr) && strpos($element->attr($attr), trim($value)) !== false) {
						array_push($nodes, $node);
					}
				}
				else {
					throw new Exception("Unkown option (".$option.")");
				}
			}
			return $nodes;
		}

		/**
		 * The following filters are supported:
		 * [attribute] Select all elements with attribute
		 * [attribute=value] Select all elements with attribute="value"
		 * [attribute^=value] Select all elements with attribute and whose value begins with "value"
		 * [attribute$=value] Select all elements with attribute and whose value ends with "value"
		 * [attribute*=value] Select all elements with attribute and whose value contains "value"
		 * ---- SO FAR ----
		 * [attribute~=value] Select all elements with attribute and whose value contains "value" as a whole word (ex: "values" will be out)
		 * [attribute|=value] Select all elements with attribute and whose value contains "value.*" as a whole word (ex: "values" will work but "thisvalue" will not) 
		 *
		 * :active
		 * ::after
		 * ::before
		 * :checked
		 * :disabled
		 * :empty - no children including text
		 * :enabled
		 * :first-child
		 * ::first-letter
		 * ::first-line
		 * :first-of-type
		 * :focus
		 * :hover
		 * :in-range
		 * :invalid
		 * :lang(language)
		 * :last-child
		 * :last-of-type
		 * :link
		 * :not(selector)
		 * :nth-child(n)
		 * :nth-last-child(n)
		 * :nth-last-of-type(n)
		 * :nth-of-type(n)
		 * :only-of-type
		 * :only-child
		 * :optional
		 * :out-of-range
		 * :read-only
		 * :read-write
		 * :required
		 * :root
		 * ::selection
		 * :target
		 * :valid
		 * :visited
		 */
		private function filterNodes($nodes, $rawFilter) {
			$nbMatch = preg_match_all('@'
					.'(?:\['
						.'(?P<attribute>['.Self::CHARSET_ATTRIBUTE.']++)\s*'
						.'(?P<operator>=|~=|\|=|\^=|\$=|\*=)?\s*'
						.'(?P<value>[^\]]++)?\s*'
					.'\])|'
					.'(?P<filter>::?[a-z0-9\-]+)\s*(?P<nested>'.Self::patternNested('\(', '\)').")"
					.'@si', $rawFilter, $matchFilter);

			for ($j = 0; $j < $nbMatch; ++$j) {
				$attribute = $matchFilter["attribute"][$j];
				$operator = $matchFilter["operator"][$j];
				$value = $matchFilter["value"][$j];
				$filter = $matchFilter["filter"][$j];
				$nested = $matchFilter["nested"][$j];

				// Select by attribute and value
				if ($attribute && $operator) {
					switch ($operator) {
					case '=':
						$nodes = $this->filterByAttribute($nodes, $attribute, $value, Self::VALUE_EQUALS);
						break;
					case '^=':
						$nodes = $this->filterByAttribute($nodes, $attribute, $value, Self::VALUE_BEGINS);
						break;
					case '$=':
						$nodes = $this->filterByAttribute($nodes, $attribute, $value, Self::VALUE_ENDS);
						break;
					case '*=':
						$nodes = $this->filterByAttribute($nodes, $attribute, $value, Self::VALUE_CONTAINS);
						break;
					default:
						throw new Exception("This operator '".$operator."' is not supported");
					}
				}
				else if ($attribute) {
					$nodes = $this->filterByAttribute($nodes, $attribute);
				}
			}
			return $nodes;
		}

		/**
		 * This function will select all elements that matches the CSS selector
		 */
		public function filter($cssSelector) {
			$nbMatch = preg_match_all('@\s*'
					.'(?P<type>[\.#]?)'
					.'(?P<name>[^\.\[\:'.Self::SEPARATOR.']*)\s*'
					.'(?P<filterList>(?:(?:\[[^\]]*\])|::?[a-z0-9\-]+'.Self::patternNested('\(', '\)').')*\s*)\s*'
					.'(?P<separator>['.Self::SEPARATOR.']?)\s*'
					.'@si', $cssSelector, $matchList);

			/* Select the DOM object that matters */
			$nodes = array($this->m_doc->getElementById("IrcmsHTML-DOMDocument-Object-Body"));

			/* If there are matches */
			for ($i = 0; $i < $nbMatch; ++$i) {
				$type = $matchList["type"][$i];
				$name = $matchList["name"][$i];
				$rawFilters = $matchList["filterList"][$i];
				$separator = $matchList["separator"][$i];

				// Ignore the entry if there is no name nor attribute
				if (!$name && !$rawFilters) {
					continue;
				}

				// Search elements
				if ($type == "." && $name) {
					$nodes = $this->searchByAttribute($nodes, "class", $name, Self::VALUE_CONTAINS_WORD);
				}
				else if ($type == "#" && $name) {
					$nodes = $this->searchByAttribute($nodes, "id", $name, Self::VALUE_EQUALS);
				}
				else if (!$type && $name) {
					$nodes = $this->searchByTagName($nodes, $name);
				}

				// Apply filters
				$nodes = $this->filterNodes($nodes, $rawFilters);

				/* Separator */
				/* element, element = AND
				 * element1 element2 = Only element2 children of element1
				 * element1 > element2 = Only element2 direct children of element1
				 * element1 + element2 = All element2 placed directly after element1
				 * element1 ~ element2 = All element2 placed after element1
				 */

				/* Add the nodes to the selection list */
				foreach ($nodes as $node) {
					array_push($this->m_selection, new IrcmsHTMLElement($node));
				}

				/* Re-build the current selection */
				/*$selection = new DOMDocument();
				foreach ($nodes as $node) {
					$selection->appendChild($selection->importNode($node, true));
				}*/

				/* Print the result */
			//	echo "\n****************\n".$match_list[0][$i]."\n";
			//	print_r(trim($new_doc->saveHTML()));
			}

			/* Assign the current selection */
		//	$this->m_selection = $selection;

			return $this;
		}

		/**
		 * Loop through the selected items
		 */
		public function each($callback) {
			$counter = 0;
			foreach ($this->m_selection as $element) {
				call_user_func($callback, $element, $counter++);
			}
			return $this;
		}

		/**
		 * Retrieve the text of the set of element
		 */
		public function text() {
			$result = "";
			foreach ($this->m_selection as $node) {
				$result .= $node->text();
			}
			return $result;
		}

		/**
		 * Return the complete HTML
		 */
		public function html() {
			$rawHtml = $this->m_doc->saveXML($this->m_doc->getElementsByTagName("div")->item(0));
			$html = substr($rawHtml, strlen(Self::BODY_START), -strlen(Self::BODY_END));
			// Convert everythign to ASCII
			if (function_exists('iconv')) {
				setlocale(LC_ALL, 'en_US.UTF8');
				$html = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $html);
			}
			return $html;
		}
	}

	/**
	 * Element class
	 */
	class IrcmsHTMLElement {
		private $m_node;
		private $m_doc;

		/**
		 * An element is always mapped to a node
		 */
		public function __construct($node) {
			$this->m_node = $node;
			$this->m_doc = $node->ownerDocument;
		}

		/**
		 * Remove all nodes and test of the current element
		 */
		public function clear() {
			while ($this->m_node->hasChildNodes()) {
				$this->m_node->removeChild($this->m_node->firstChild);
			}
			$this->m_node->nodeValue = "";
		}

		/**
		 * Set or return the text associated with this element
		 */
		public function text($text = null) {
			if ($text === null) {
				$text = $this->m_node->nodeValue;
				if (function_exists('iconv')) {
					setlocale(LC_ALL, 'en_US.UTF8');
					$text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
				}
				return trim($text);
			}
			$this->m_node->nodeValue = $text;
		}

		/**
		 * Set or return the HTML content associated with this element
		 */
		public function html($html = null) {
			if ($html === null) {
				$str = "";
				foreach ($this->m_node->childNodes as $node) {
					$str .= $this->m_doc->saveXML($node);
				}
				return $str;
			}

			$frag = $this->m_doc->createDocumentFragment();
			$frag->appendXML($html);
			$this->clear();
			$this->m_node->appendChild($frag);
		}

		/**
		 * Get or set an attribute to a node
		 */
		public function attr($name, $value = null) {
			if ($value === null) {
				if (!$this->hasAttr($name)) {
					return null;
				}
				return trim($this->m_node->getAttribute($name));
			}
			$this->m_node->setAttribute($name, $value);
		}

		/**
		 * Check if an element has the attribute
		 */
		public function hasAttr($name) {
			return $this->m_node->hasAttribute($name);
		}

		/**
		 * \brief Helper function to generate a string pattern
		 *
		 * A string pattern will match on a single entity defined by the charset, or
		 * anything delimited by single or double quotes.
		 */
		private static function patternString($charset = "[a-z0-9_]++") {
			return "\s*(".$charset."|\"([^\"]|(?<=\\\\)\")*+\"|'([^']|(?<=\\\\)')*+'|)";
		}

		/**
		 * This function create an element from a string passed in argument
		 */
		private function createElement($elt_str) {
			// Default value
			$elt = null;
			// Match the tag name
			$nb_match = preg_match("@^\s*<\s*(?P<name>[a-z0-9\-]+)\s*(?P<attributeList>[^>]*)\s*/?>((?P<content>.*)</[^>]+>)?$@si", $elt_str, $match_list);
			if ($nb_match) {
				$name = $match_list["name"];
				$attributeList = $match_list["attributeList"];
				$content = (isset($match_list["content"])) ? $match_list["content"] : "";
				// Create the element
				$elt = $this->m_doc->createElement($name);
				// Parse the attributes
				$nb_match = preg_match_all("@(?P<attribute>[a-z\-_]+)\s*=\s*(?P<value>".Self::patternString().")@si", $attributeList, $match_list);
				for ($i = 0; $i < $nb_match; ++$i) {
					$attribute = $match_list["attribute"][$i];
					$value = trim($match_list["value"][$i], "\"'");
					$elt->setAttribute($attribute, $value);
				}
				// Set the value of the node
				$elt->nodeValue = $content;
			}
			// Check if the element as been correctly constructed
			if (!$elt) {
				throw new Exception("The element `".$elt_str."' cannot be constructed.");
			}
			return $elt;
		}

		/**
		 * Replace the current element with some HTML content
		 */
		public function replaceWith($html) {
			/* Parse the string element */
			$elt = $this->createElement($html);
			$this->m_node->parentNode->replaceChild($elt, $this->m_node);
		}

		/**
		 * Wrap an element around the current node
		 */
		public function wrap($html) {
			/* Parse the string element */
			$elt = $this->createElement($html);
			$this->m_node->parentNode->replaceChild($elt, $this->m_node);
			$elt->appendChild($this->m_node);
		}

		/**
		 * Insert an element before the current one
		 */
		public function before($html) {
			/* Parse the string element */
			$elt = $this->createElement($html);
			$this->m_node->parentNode->insertBefore($elt, $this->m_node);
		}
	}
?>