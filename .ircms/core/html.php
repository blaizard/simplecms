<?php
	class IrcmsHTML {
		const SEPARATOR = ",\s+>~";
		const CHARSET_ATTRIBUTE = "a-z0-9\-_";

		const VALUE_MASK = 0x0f;
		const VALUE_EQUALS = 1;
		const VALUE_CONTAINS = 2;
		const VALUE_CONTAINS_WORD = 3;
		const VALUE_BEGINS = 4;
		const VALUE_ENDS = 5;

		const FILTER_MASK = 0xf0;
		const FILTER_ALL = 0x00;
		const FILTER_ONLY_CHILDREN = 0x10;

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
			$this->m_doc->preserveWhiteSpace = true;
			$this->m_doc->formatOutput = false;
			$this->m_doc->strictErrorChecking = false;
			@$this->m_doc->loadHTML(Self::BODY_START.$html.Self::BODY_END, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			$this->m_xpath = new DomXPath($this->m_doc);
			$this->m_selection = array();
		}

		/**
		 * Creates an element
		 */
		public function createElement($html) {
			return new IrcmsHTMLElement($html, $this->m_doc);
		}

		/**
		 * \brief Helper function to generate a nested pattern
		 */
		private static function patternNested($opening_char, $closing_char, $id = null) {
			static $id = 0;
			$id++;
			return "(?<patternNested".$id.">".$opening_char."(?:(?".">[^".$opening_char.$closing_char."]*+)|(?P>patternNested".$id."))*".$closing_char.")";
		}

		/**
		 * Find all the nodes that matches with the tag name
		 */
		private function searchByTagName($selection, $name, $option = Self::FILTER_ALL) {
			$nodes = array();
			foreach ($selection as $node) {
				$result = $node->getElementsByTagName($name);
				foreach ($result as $n) {
					if (($option & Self::FILTER_MASK) == Self::FILTER_ONLY_CHILDREN && $n->parentNode !== $node) {
						continue;
					}
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
			$xPathSelector = (($option & Self::FILTER_MASK) == Self::FILTER_ONLY_CHILDREN) ? "/" : "//";
			foreach ($selection as $node) {
				if ($value === null || $value == "*") {
					$result = $this->m_xpath->query($xPathSelector."*[@".$attr."]", $node);
				}
				else if (($option & Self::VALUE_MASK) == Self::VALUE_EQUALS) {
					$result = $this->m_xpath->query($xPathSelector."*[@".$attr."='".$value."']", $node);
				}
				else if (($option & Self::VALUE_MASK) == Self::VALUE_CONTAINS) {
					$result = $this->m_xpath->query($xPathSelector."*[contains(@".$attr.", '".$value."')]", $node);
				}
				else if (($option & Self::VALUE_MASK) == Self::VALUE_CONTAINS_WORD) {
					$result = $this->m_xpath->query($xPathSelector."*[contains(concat(' ', normalize-space(@".$attr."), ' '), ' ".$value." ')]", $node);
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
				else if (($option & Self::VALUE_MASK) == Self::VALUE_EQUALS) {
					if ($element->hasAttr($attr) && $element->attr($attr) == trim($value)) {
						array_push($nodes, $node);
					}
				}
				else if (($option & Self::VALUE_MASK) == Self::VALUE_BEGINS) {
					if ($element->hasAttr($attr) && strpos($element->attr($attr), trim($value)) === 0) {
						array_push($nodes, $node);
					}
				}
				else if (($option & Self::VALUE_MASK) == Self::VALUE_ENDS) {
					if ($element->hasAttr($attr) && substr($element->attr($attr), -strlen(trim($value))) == trim($value)) {
						array_push($nodes, $node);
					}
				}
				else if (($option & Self::VALUE_MASK) == Self::VALUE_CONTAINS) {
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
		 * Filters a specific selection of 
		 */
		private function filterSelection($nodes, $type, $name, $rawFilters, $filterFlags) {
			// Search elements
			if ($type == "." && $name) {
				$nodes = $this->searchByAttribute($nodes, "class", $name, Self::VALUE_CONTAINS_WORD | $filterFlags);
			}
			else if ($type == "#" && $name) {
				$nodes = $this->searchByAttribute($nodes, "id", $name, Self::VALUE_EQUALS | $filterFlags);
			}
			else if (!$type && $name) {
				$nodes = $this->searchByTagName($nodes, $name, $filterFlags);
			}

			// Apply filters
			$nodes = $this->filterNodes($nodes, $rawFilters);

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

			// Select the DOM object that matters
			$nodes = array($this->m_doc->getElementById("IrcmsHTML-DOMDocument-Object-Body"));
			$currentNodes = $nodes;
			$filterFlags = Self::FILTER_ALL;

			// If there are matches
			for ($i = 0; $i < $nbMatch; ++$i) {
				$type = $matchList["type"][$i];
				$name = $matchList["name"][$i];
				$rawFilters = $matchList["filterList"][$i];
				$separator = $matchList["separator"][$i];

				// Ignore the entry if there is no name nor attribute
				if (!$name && !$rawFilters) {
					continue;
				}

				// Apply filter on the selection
				$currentNodes = $this->filterSelection($currentNodes, $type, $name, $rawFilters, $filterFlags);

				// Separator
				switch ($separator) {
				case ">":
					$filterFlags = Self::FILTER_ONLY_CHILDREN;
					break;
				case "+":
					break;
				case "~":
					break;
				// Add the nodes to the selection list
				case ",":
				case "":
					foreach ($currentNodes as $node) {
						array_push($this->m_selection, new IrcmsHTMLElement($node));
					}
					$currentNodes = $nodes;
					$filterFlags = Self::FILTER_ALL;
					break;
				default:
					if (preg_match('/\s+/', $separator)) {
					}
					else {
						throw new Exception("Unknown filter separator `".$separator."'");
					}
				}
				/* element, element = AND
				 * element1 element2 = Only element2 children of element1
				 * element1 > element2 = Only element2 direct children of element1
				 * element1 + element2 = All element2 placed directly after element1
				 * element1 ~ element2 = All element2 placed after element1
				 */

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
			$rawHtml = $this->m_doc->saveHTML();
			$html = substr($rawHtml, strlen(Self::BODY_START), -strlen(Self::BODY_END)-1);
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
		public function __construct($node, $doc = null) {
			if ($doc !== null) {
				$this->m_doc = $doc;
				$this->m_node = $this->createElement($node);
			}
			else {
				$this->m_node = $node;
				$this->m_doc = $node->ownerDocument;
			}
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
			return $this->htmlInternal($this->m_node, $html);
		}

		/**
		 * \internal
		 */
		private function htmlInternal($n, $html = null) {
			if ($html === null) {
				$str = "";
				foreach ($n->childNodes as $node) {
					$str .= $this->m_doc->saveHTML($node);
				}
				return $str;
			}

			$frag = $this->m_doc->createDocumentFragment();
			$html = $this->htmlConvertEntities($html);
			$frag->appendXML($html);
			$this->clear();
			$n->appendChild($frag);
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
		private function createElement($eltStr) {
			// Default value
			$elt = null;
			// Match the tag name
			$nb_match = preg_match("@^\s*<\s*(?P<name>[a-z0-9\-]+)\s*(?P<attributeList>[^>]*)\s*/?>((?P<content>.*)</[^>]+>)?$@si", $eltStr, $match_list);
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
				//$this->htmlInternal($elt, $content);
			}
			// Check if the element as been correctly constructed
			if (!$elt) {
				throw new Exception("The element `".$eltStr."' cannot be constructed.");
			}
			return $elt;
		}

		/**
		 * Replace the current element with some HTML content
		 */
		public function replaceWith($html) {
			// Parse the string element
			$elt = (is_string($html)) ? $this->createElement($html) : $html->m_node;
			$this->m_node->parentNode->replaceChild($elt, $this->m_node);
			$this->m_node = $elt;
		}

		/**
		 * Wrap an element around the current node
		 */
		public function wrap($html) {
			// Parse the string element
			$elt = (is_string($html)) ? $this->createElement($html) : $html->m_node;
			$this->m_node->parentNode->replaceChild($elt, $this->m_node);
			$elt->appendChild($this->m_node);
		}

		/**
		 * Insert an element before the current one
		 */
		public function before($html) {
			// Parse the string element
			$elt = (is_string($html)) ? $this->createElement($html) : $html->m_node;
			$this->m_node->parentNode->insertBefore($elt, $this->m_node);
		}

		/**
		 * Select the parent node if any
		 */
		public function parent() {
			$this->m_node = $this->m_node->parentNode;
			return $this;
		}


		/* html_convert_entities($string) -- convert named HTML entities to 
		 * XML-compatible numeric entities.
		 * \author inanimatt
		 */
		private function htmlConvertEntities($string) {
			return preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]+);/S', array("Self", "convertEntity"), $string);
		}

		/* Swap HTML named entity with its numeric equivalent. If the entity
		 * isn't in the lookup table, this function returns a blank, which
		 * destroys the character in the output - this is probably the 
		 * desired behaviour when producing XML.
		 * \author inanimatt
		 */
		private static function convertEntity($matches) {
			static $table = array('quot'    => '&#34;',
				'amp'      => '&#38;',
				'lt'       => '&#60;',
				'gt'       => '&#62;',
				'OElig'    => '&#338;',
				'oelig'    => '&#339;',
				'Scaron'   => '&#352;',
				'scaron'   => '&#353;',
				'Yuml'     => '&#376;',
				'circ'     => '&#710;',
				'tilde'    => '&#732;',
				'ensp'     => '&#8194;',
				'emsp'     => '&#8195;',
				'thinsp'   => '&#8201;',
				'zwnj'     => '&#8204;',
				'zwj'      => '&#8205;',
				'lrm'      => '&#8206;',
				'rlm'      => '&#8207;',
				'ndash'    => '&#8211;',
				'mdash'    => '&#8212;',
				'lsquo'    => '&#8216;',
				'rsquo'    => '&#8217;',
				'sbquo'    => '&#8218;',
				'ldquo'    => '&#8220;',
				'rdquo'    => '&#8221;',
				'bdquo'    => '&#8222;',
				'dagger'   => '&#8224;',
				'Dagger'   => '&#8225;',
				'permil'   => '&#8240;',
				'lsaquo'   => '&#8249;',
				'rsaquo'   => '&#8250;',
				'euro'     => '&#8364;',
				'fnof'     => '&#402;',
				'Alpha'    => '&#913;',
				'Beta'     => '&#914;',
				'Gamma'    => '&#915;',
				'Delta'    => '&#916;',
				'Epsilon'  => '&#917;',
				'Zeta'     => '&#918;',
				'Eta'      => '&#919;',
				'Theta'    => '&#920;',
				'Iota'     => '&#921;',
				'Kappa'    => '&#922;',
				'Lambda'   => '&#923;',
				'Mu'       => '&#924;',
				'Nu'       => '&#925;',
				'Xi'       => '&#926;',
				'Omicron'  => '&#927;',
				'Pi'       => '&#928;',
				'Rho'      => '&#929;',
				'Sigma'    => '&#931;',
				'Tau'      => '&#932;',
				'Upsilon'  => '&#933;',
				'Phi'      => '&#934;',
				'Chi'      => '&#935;',
				'Psi'      => '&#936;',
				'Omega'    => '&#937;',
				'alpha'    => '&#945;',
				'beta'     => '&#946;',
				'gamma'    => '&#947;',
				'delta'    => '&#948;',
				'epsilon'  => '&#949;',
				'zeta'     => '&#950;',
				'eta'      => '&#951;',
				'theta'    => '&#952;',
				'iota'     => '&#953;',
				'kappa'    => '&#954;',
				'lambda'   => '&#955;',
				'mu'       => '&#956;',
				'nu'       => '&#957;',
				'xi'       => '&#958;',
				'omicron'  => '&#959;',
				'pi'       => '&#960;',
				'rho'      => '&#961;',
				'sigmaf'   => '&#962;',
				'sigma'    => '&#963;',
				'tau'      => '&#964;',
				'upsilon'  => '&#965;',
				'phi'      => '&#966;',
				'chi'      => '&#967;',
				'psi'      => '&#968;',
				'omega'    => '&#969;',
				'thetasym' => '&#977;',
				'upsih'    => '&#978;',
				'piv'      => '&#982;',
				'bull'     => '&#8226;',
				'hellip'   => '&#8230;',
				'prime'    => '&#8242;',
				'Prime'    => '&#8243;',
				'oline'    => '&#8254;',
				'frasl'    => '&#8260;',
				'weierp'   => '&#8472;',
				'image'    => '&#8465;',
				'real'     => '&#8476;',
				'trade'    => '&#8482;',
				'alefsym'  => '&#8501;',
				'larr'     => '&#8592;',
				'uarr'     => '&#8593;',
				'rarr'     => '&#8594;',
				'darr'     => '&#8595;',
				'harr'     => '&#8596;',
				'crarr'    => '&#8629;',
				'lArr'     => '&#8656;',
				'uArr'     => '&#8657;',
				'rArr'     => '&#8658;',
				'dArr'     => '&#8659;',
				'hArr'     => '&#8660;',
				'forall'   => '&#8704;',
				'part'     => '&#8706;',
				'exist'    => '&#8707;',
				'empty'    => '&#8709;',
				'nabla'    => '&#8711;',
				'isin'     => '&#8712;',
				'notin'    => '&#8713;',
				'ni'       => '&#8715;',
				'prod'     => '&#8719;',
				'sum'      => '&#8721;',
				'minus'    => '&#8722;',
				'lowast'   => '&#8727;',
				'radic'    => '&#8730;',
				'prop'     => '&#8733;',
				'infin'    => '&#8734;',
				'ang'      => '&#8736;',
				'and'      => '&#8743;',
				'or'       => '&#8744;',
				'cap'      => '&#8745;',
				'cup'      => '&#8746;',
				'int'      => '&#8747;',
				'there4'   => '&#8756;',
				'sim'      => '&#8764;',
				'cong'     => '&#8773;',
				'asymp'    => '&#8776;',
				'ne'       => '&#8800;',
				'equiv'    => '&#8801;',
				'le'       => '&#8804;',
				'ge'       => '&#8805;',
				'sub'      => '&#8834;',
				'sup'      => '&#8835;',
				'nsub'     => '&#8836;',
				'sube'     => '&#8838;',
				'supe'     => '&#8839;',
				'oplus'    => '&#8853;',
				'otimes'   => '&#8855;',
				'perp'     => '&#8869;',
				'sdot'     => '&#8901;',
				'lceil'    => '&#8968;',
				'rceil'    => '&#8969;',
				'lfloor'   => '&#8970;',
				'rfloor'   => '&#8971;',
				'lang'     => '&#9001;',
				'rang'     => '&#9002;',
				'loz'      => '&#9674;',
				'spades'   => '&#9824;',
				'clubs'    => '&#9827;',
				'hearts'   => '&#9829;',
				'diams'    => '&#9830;',
				'nbsp'     => '&#160;',
				'iexcl'    => '&#161;',
				'cent'     => '&#162;',
				'pound'    => '&#163;',
				'curren'   => '&#164;',
				'yen'      => '&#165;',
				'brvbar'   => '&#166;',
				'sect'     => '&#167;',
				'uml'      => '&#168;',
				'copy'     => '&#169;',
				'ordf'     => '&#170;',
				'laquo'    => '&#171;',
				'not'      => '&#172;',
				'shy'      => '&#173;',
				'reg'      => '&#174;',
				'macr'     => '&#175;',
				'deg'      => '&#176;',
				'plusmn'   => '&#177;',
				'sup2'     => '&#178;',
				'sup3'     => '&#179;',
				'acute'    => '&#180;',
				'micro'    => '&#181;',
				'para'     => '&#182;',
				'middot'   => '&#183;',
				'cedil'    => '&#184;',
				'sup1'     => '&#185;',
				'ordm'     => '&#186;',
				'raquo'    => '&#187;',
				'frac14'   => '&#188;',
				'frac12'   => '&#189;',
				'frac34'   => '&#190;',
				'iquest'   => '&#191;',
				'Agrave'   => '&#192;',
				'Aacute'   => '&#193;',
				'Acirc'    => '&#194;',
				'Atilde'   => '&#195;',
				'Auml'     => '&#196;',
				'Aring'    => '&#197;',
				'AElig'    => '&#198;',
				'Ccedil'   => '&#199;',
				'Egrave'   => '&#200;',
				'Eacute'   => '&#201;',
				'Ecirc'    => '&#202;',
				'Euml'     => '&#203;',
				'Igrave'   => '&#204;',
				'Iacute'   => '&#205;',
				'Icirc'    => '&#206;',
				'Iuml'     => '&#207;',
				'ETH'      => '&#208;',
				'Ntilde'   => '&#209;',
				'Ograve'   => '&#210;',
				'Oacute'   => '&#211;',
				'Ocirc'    => '&#212;',
				'Otilde'   => '&#213;',
				'Ouml'     => '&#214;',
				'times'    => '&#215;',
				'Oslash'   => '&#216;',
				'Ugrave'   => '&#217;',
				'Uacute'   => '&#218;',
				'Ucirc'    => '&#219;',
				'Uuml'     => '&#220;',
				'Yacute'   => '&#221;',
				'THORN'    => '&#222;',
				'szlig'    => '&#223;',
				'agrave'   => '&#224;',
				'aacute'   => '&#225;',
				'acirc'    => '&#226;',
				'atilde'   => '&#227;',
				'auml'     => '&#228;',
				'aring'    => '&#229;',
				'aelig'    => '&#230;',
				'ccedil'   => '&#231;',
				'egrave'   => '&#232;',
				'eacute'   => '&#233;',
				'ecirc'    => '&#234;',
				'euml'     => '&#235;',
				'igrave'   => '&#236;',
				'iacute'   => '&#237;',
				'icirc'    => '&#238;',
				'iuml'     => '&#239;',
				'eth'      => '&#240;',
				'ntilde'   => '&#241;',
				'ograve'   => '&#242;',
				'oacute'   => '&#243;',
				'ocirc'    => '&#244;',
				'otilde'   => '&#245;',
				'ouml'     => '&#246;',
				'divide'   => '&#247;',
				'oslash'   => '&#248;',
				'ugrave'   => '&#249;',
				'uacute'   => '&#250;',
				'ucirc'    => '&#251;',
				'uuml'     => '&#252;',
				'yacute'   => '&#253;',
				'thorn'    => '&#254;',
				'yuml'     => '&#255;');
			// Entity not found? Destroy it.
			return isset($table[$matches[1]]) ? $table[$matches[1]] : '';
		}
	}
?>