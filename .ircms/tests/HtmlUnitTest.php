<?php
	/**
	 * Returns a parseable result of the object
	 */
	function helperGetResult($html) {
		$result = array();
		$html->each(function($elt) use (&$result) {
			array_push($result, $elt->text());
		});
		return $result;
	}

	class HtmlUnitTest extends \PHPUnit_Framework_TestCase {

		/**
		 * Tests single element selector (without filters or combination)
		 */
		public function testSingleElementSelector() {

			// Match nothing
			{
				$html = new IrcmsHtml("<div><a>Hello</a><a>World</a></div>");
				$html->filter("u");
				$this->assertEquals(array(), helperGetResult($html));
			}

			// tag name selector
			{
				$html = new IrcmsHtml("<div><a>Hello</a><a>World</a></div>");
				$html->filter("a");
				$this->assertEquals(array("Hello", "World"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div>Hello<div>Yes</div></div><blockquote><div>Ok</div></blockquote><div>World</div>");
				$html->filter("div");
				$this->assertEquals(array("HelloYes", "Yes", "Ok", "World"), helperGetResult($html));
			}

			// id selector
			{
				$html = new IrcmsHtml("<div>Hello<div id=\"meToo\">Yes</div></div><blockquote><div id=\"me\">Ok</div></blockquote><div>World</div>");
				$html->filter("#me");
				$this->assertEquals(array("Ok"), helperGetResult($html));
			}

			// class selector
			{
				$html = new IrcmsHtml("<div>Hello<div class=\"myClass\">Yes</div></div><blockquote><div class=\"myClass\">Ok</div></blockquote><div>World</div>");
				$html->filter(".myClass");
				$this->assertEquals(array("Yes", "Ok"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div>Hello<div class=\"firstClass myClass anotherClass\">Yes</div></div><div class=\"myClassYesMine\">Ok</div><div>World</div>");
				$html->filter(".myClass");
				$this->assertEquals(array("Yes"), helperGetResult($html));
			}
		}

		/**
		 * Tests single element selector with filters
		 */
		public function testSingleElementSelectorFilter() {
			// Match attribute
			{
				$html = new IrcmsHtml("<div><a data-test>Hello</a><a>World</a></div>");
				$html->filter("a[data-test]");
				$this->assertEquals(array("Hello"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div><a data-test-yes>Hello</a><p data-test>Nop</p><a data-test>World</a><a data-test=\"this\">Lap</a></div>");
				$html->filter("a[data-test]");
				$this->assertEquals(array("World", "Lap"), helperGetResult($html));
			}

			// Match attribute = value
			{
				$html = new IrcmsHtml("<div><a data-test=\"yes\">Hello</a><a data-test=\"no\">World</a><a data-test='yes'>Oki</a></div>");
				$html->filter("a[data-test=yes]");
				$this->assertEquals(array("Hello", "Oki"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div><a data-test=\"yes\">Hello</a><a data-test=\"\">World</a><a data-test='yes'>Oki</a></div>");
				$html->filter("a[data-test=]");
				$this->assertEquals(array("World"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div><a data-test=\"yes\">Hello</a><a data-test=\"no\">World</a><a data-test='yes'>Oki</a></div>");
				$html->filter("a[data-test=*]");
				$this->assertEquals(array("Hello", "World", "Oki"), helperGetResult($html));
			}

			// Match attribute ^= value
			{
				$html = new IrcmsHtml("<div><a href=\"https://yes\">Hello</a><a href=\"ssh://no\">World</a><a href='http://yes'>Oki</a></div>");
				$html->filter("a[href^=http://]");
				$this->assertEquals(array("Oki"), helperGetResult($html));
			}
			// Match attribute $= value
			{
				$html = new IrcmsHtml("<div><a href=\"https://yes\">Hello</a><a href=\"ssh://no\">World</a><a href='http://yes'>Oki</a></div>");
				$html->filter("a[href$=yes]");
				$this->assertEquals(array("Hello", "Oki"), helperGetResult($html));
			}
			// Match attribute *= value
			{
				$html = new IrcmsHtml("<div><a href=\"abcdefgh\">Hello</a><a href=\"opqrst\">World</a><a href='hijklmnop'>Oki</a></div>");
				$html->filter("a[href*=h]");
				$this->assertEquals(array("Hello", "Oki"), helperGetResult($html));
			}
		}

		/**
		 * Tests element text and html assignation and retrieval
		 */
		public function testElementHTMLAndText() {
			// Element get text
			{
				$html = new IrcmsHtml("<div><a>Hello</a><a>World</a></div>");
				$html->filter("a");
				$this->assertEquals(array("Hello", "World"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div><a>Hello <div>Yes</div></a><a>World</a></div>");
				$html->filter("a");
				$this->assertEquals(array("Hello Yes", "World"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div>Hello <div>Yes</div></div><div>World</div>");
				$html->filter("div");
				$this->assertEquals(array("Hello Yes", "Yes", "World"), helperGetResult($html));
			}
			// Element set text
			{
				$html = new IrcmsHtml("<div><a>Hello</a><a>World</a></div>");
				$html->filter("a")->each(function($elt, $i) {
					$set = array("New", "Text");
					$elt->text($set[$i]);
				});
				$this->assertEquals("<div><a>New</a><a>Text</a></div>", $html->html());
			}
			{
				$html = new IrcmsHtml("<div>Hello <div>Yes</div></div><div>World</div>");
				$html->filter("div")->each(function($elt, $i) {
					$elt->text("abcd");
				});
				$this->assertEquals("<div>abcd</div><div>abcd</div>", $html->html());
			}
			// Element get HTML
			{
				$html = new IrcmsHtml("<div data-test>Hello <div>Yes</div>World</div>");
				$html->filter("div[data-test]")->each(function($elt, $i) {
					$this->assertEquals("Hello <div>Yes</div>World", $elt->html());
				});
			}
			{
				$html = new IrcmsHtml("<div><script>alert(\"hello\");</script></div>");
				$html->filter("div")->each(function($elt, $i) {
					$this->assertEquals("<script>alert(\"hello\");</script>", $elt->html());
				});
			}
			// Element set HTML
			{
				$html = new IrcmsHtml("<a>Not</a><div>Yes</div>");
				$html->filter("div")->each(function($elt, $i) {
					$elt->html("Hello <a href=\"yes\">World</a>");
				});
				$this->assertEquals("<a>Not</a><div>Hello <a href=\"yes\">World</a></div>", $html->html());
			}
			{
				$html = new IrcmsHtml("<a>Not</a><div>Yes</div>");
				$html->filter("div")->each(function($elt, $i) {
					$elt->html("World");
				});
				$this->assertEquals("<a>Not</a><div>World</div>", $html->html());
			}
			{
				$html = new IrcmsHtml("<a>Not</a><div>Yes</div>");
				$html->filter("div")->each(function($elt, $i) {
					$elt->html("&nbsp;");
				});
				$this->assertEquals("<a>Not</a><div>&nbsp;</div>", $html->html());
			}
		}

		/**
		 * Tests element text and html assignation and retrieval
		 */
		public function testDocumentHTMLAndText() {
			// Document get HTML
			{
				$rawHtml = "<div data-test>Hello <div>Yes</div>World</div>";
				$html = new IrcmsHtml($rawHtml);
				$this->assertEquals($rawHtml, $html->html());
			}
			{
				$rawHtml = "<div><script>alert(\"hello\");</script></div>";
				$html = new IrcmsHtml($rawHtml);
				$this->assertEquals($rawHtml, $html->html());
			}
		}

		/**
		 * Tests element text and html assignation and retrieval
		 */
		public function testSeparators() {
			// Select directly after >
			{
				$html = new IrcmsHtml("<div><a>Hello</a></div><a>World</a>");
				$html->filter("div > a");
				$this->assertEquals(array("Hello"), helperGetResult($html));
			}
			{
				$html = new IrcmsHtml("<div>1<div>2<a>Hello</a></div></div><a>World</a><div><a>Yes</a></div>");
				$html->filter("div > a");
				$this->assertEquals(array("Hello", "Yes"), helperGetResult($html));
			}
		}
	}
?>