<?php
	class PathUnitTest extends \PHPUnit_Framework_TestCase {

		public function testToUrl() {
			/* Basic */
			$result = IrcmsPath::toUrl("/hello/");
			$this->assertEquals("/hello/", $result);
			$result = IrcmsPath::toUrl("/hello world/");
			$this->assertEquals("/hello+world/", $result);
		}

		public function testGetRelativePath() {
			/* Basic */
			$result = IrcmsPath::getRelativePath("/", "/hello/world");
			$this->assertEquals("hello/world", $result);
			$result = IrcmsPath::getRelativePath("/hello/world/yes", "/hello/world");
			$this->assertEquals(null, $result);
			/* Path not cleaned */
			$result = IrcmsPath::getRelativePath("/world/", "/hello/../world/yes");
			$this->assertEquals("yes", $result);
			$result = IrcmsPath::getRelativePath("/world/hello/../././", "/world/yes");
			$this->assertEquals("yes", $result);
			/* Test corner cases */
			$result = IrcmsPath::getRelativePath("", "");
			$this->assertEquals("", $result);
		}

		public function testClean() {
			/* Basic */
			$result = IrcmsPath::clean("/hello/world");
			$this->assertEquals("/hello/world", $result);
			$result = IrcmsPath::clean("hello/world/");
			$this->assertEquals("hello/world/", $result);
			$result = IrcmsPath::clean("hello/");
			$this->assertEquals("hello/", $result);
			$result = IrcmsPath::clean("/hello");
			$this->assertEquals("/hello", $result);
			/* Test corner cases */
			$result = IrcmsPath::clean("");
			$this->assertEquals("", $result);
			$result = IrcmsPath::clean("/");
			$this->assertEquals("/", $result);
			$result = IrcmsPath::clean(".");
			$this->assertEquals("", $result);
			$result = IrcmsPath::clean("./");
			$this->assertEquals("", $result);
			$result = IrcmsPath::clean("../");
			$this->assertEquals("../", $result);
			/* Path not cleaned */
			$result = IrcmsPath::clean("/hello/../world/yes");
			$this->assertEquals("/world/yes", $result);
			$result = IrcmsPath::clean(".////./hello/../world/yes");
			$this->assertEquals("world/yes", $result);
			$result = IrcmsPath::clean("/.////./hello/../world/yes");
			$this->assertEquals("/world/yes", $result);
			$result = IrcmsPath::clean("../../../world/yes");
			$this->assertEquals("../../../world/yes", $result);
			$result = IrcmsPath::clean("../world/.././../world/yes");
			$this->assertEquals("../../world/yes", $result);
		}
	}
?>