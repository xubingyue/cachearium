<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

require_once(__DIR__ . '/../Cache.php');

function cacheCallback() {
	static $count = 1;
	$count++;
	if ($count % 2) {
		return 'aaaa';
	}
	else {
		return 'bbbb';
	}
}

class CacheTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;
	protected static $enabled;
	protected static $testMemoryCache;

	static public function setUpBeforeClass() {
		global $FLOH_CONFIG;
		self::$testMemoryCache = true; // CONF('LH_CACHE_ENABLE') && CONF('LH_CACHE_MEMCACHE');
		self::$enabled = true; // CONF('LH_CACHE_ENABLE');
		$FLOH_CONFIG['LH_CACHE_DEBUG_ONPAGE'] = false;
		if (!self::$enabled) {
			self::markTestSkipped('CacheAbstract disabled');
		}
		else {
			CacheMemcached::singleton([['localhost', 11211]]); // set server
			CacheAbstract::clearAll();
		}
	}

	static public function tearDownAfterClass() {
	}

	protected function setUp() {
		// ob_start();
	}

	protected function tearDown() {
		// ob_end_clean();
	}

	private function setGetClean(CacheAbstract $cache) {
		$base = 'base';

		$this->assertTrue($cache->isEnabled());
		$cache->setDefaultLifetime(3600);
		$this->assertEquals(3600, $cache->getDefaultLifetime());
		$cache->clean($base, 1);

		try {
			$data = $cache->get($base, 1);
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}

		$retval = $cache->save(234, $base, 1);
		$this->assertTrue($retval);

		try {
			$data = $cache->get($base, 1);
			$this->assertEquals(234, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		sleep(1);

		try {
			$data = $cache->get($base, 1);
			$this->assertEquals(234, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		$cache->clean($base, 1);
		try {
			$data = $cache->get($base, 1);
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}

		// now change again and delete
		$retval = $cache->save(234, $base, 2, 'a');
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->get($base, 2, 'a');
			$this->assertEquals(234, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->delete($base, 2, 'a'));

		// test null
		$retval = $cache->save(null, $base, 3, 'a');
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->get($base, 3, 'a');
			$this->assertEquals(null, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->delete($base, 3, 'a'));
	}

	public function testSetGetCleanRAM() {
		$cache = CacheRAM::singleton();
		$this->setGetClean($cache);
	}

	public function testSetGetCleanFS() {
		$cache = CacheFS::singleton();
		$this->setGetClean($cache);
	}

	public function testSetGetCleanMem() {
		if (self::$testMemoryCache) {
			$cache = CacheMemcached::singleton([['localhost', 11211]]);
			$this->setGetClean($cache);
		}
	}

	public function testPrefetch() {
		if (!self::$testMemoryCache) {
			return;
		}

		$key = new CacheKey('baseprefetch', 'idprefetch', 'subprefetch');
		$data = '92348ijasd2q3r';

		$cache = CacheMemcached::singleton([['localhost', 11211]]);

		// save.
		$retval = $cache->storeK($data, $key);
		$this->assertEquals(true, $retval);

		// get. We should have a local cache.
		$startfetches = $cache->getFetches();
		$data2 = $cache->getK($key);
		$this->assertEquals($data, $data2);
		$this->assertEquals($startfetches, $cache->getFetches());

		// now clear the cache. and prefetch
		$cache->prefetchClear();
		$cache->prefetch(
			array($key)
		);

		$this->markTestSkipped();
		// get. We should have a local cache.
		$startfetches = $cache->getFetches();
		$data2 = $cache->getK($key);
		$this->assertEquals($data, $data2);
		$this->assertEquals($startfetches, $cache->getFetches());
	}

	private function startEnd(CacheAbstract $cache) {
		$base = 'startend';
		$cache->clean($base, 1);

		$this->assertFalse($cache->recursivestart($base, 1));
		echo 'start!';
		$cache->recursiveend(false);

		ob_start();
		ob_implicit_flush(false);
		$this->assertTrue($cache->recursivestart($base, 1));
		$data = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('start!', $data);
		$cache->clean($base, 1);
	}

	public function testStartEndRAM() {
		$cache = CacheRAM::singleton();
		$this->startEnd($cache);
	}

	public function testStartEndFS() {
		$cache = CacheFS::singleton();
		$this->startEnd($cache);
	}

	public function testStartEndMem() {
		if (self::$testMemoryCache) {
			$cache = CacheMemcached::singleton([['localhost', 11211]]);
			$this->startEnd($cache);
		}
	}

	private function serialize(CacheAbstract $cache) {
		$base = 'serialize';

		$cache->clean($base, 1);

		$data = array('awer' => 132,
			array('awerawer' => 23423,
				'cvbxxcv' => 234,
			)
		);
		$retval = $cache->save($data, $base, 1);
		$this->assertEquals(true, $retval);

		$data2 = $cache->get($base, 1);
		$this->assertEquals($data2, $data);
	}

	public function testSerializeRAM() {
		$cache = CacheRAM::singleton();
		$this->serialize($cache);
	}

	public function testSerializeFS() {
		$cache = CacheFS::singleton();
		$this->serialize($cache);
	}

	public function testSerializeMem() {
		if (self::$testMemoryCache) {
			$cache = CacheMemcached::singleton([['localhost', 11211]]);
			$this->serialize($cache);
		}
	}

	private function setBigClean(CacheAbstract $cache) {
		$id = '2';
		$base = 'bigclean';
		$otherid = '3';

		$retval = $cache->save(111, $base, $id, 'a');
		$this->assertEquals(true, $retval);
		$retval = $cache->save(222, $base, $id, 'b');
		$this->assertEquals(true, $retval);
		$retval = $cache->save(333, $base, $otherid, 'a');
		$this->assertEquals(true, $retval);

		$data = $cache->get($base, $id, 'a');
		$this->assertEquals(111, $data);
		$data = $cache->get($base, $id, 'b');
		$this->assertEquals(222, $data);
		$data = $cache->get($base, $otherid, 'a');
		$this->assertEquals(333, $data);

		$cache->clean($base, $id);

		try {
			$data = $cache->get($base, $id, 'a');
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}
		try {
			$data = $cache->get($base, $otherid, 'a');
			$this->assertEquals(333, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		try {
			$data = $cache->get($base, $id, 'b');
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}
	}

	private function setBigClear(CacheAbstract $cache) {
		$id = '2';
		$base = 'bigclean';
		$otherid = '3';
		$otherbase = 'bigfoo';

		$retval = $cache->save(111, $base, $id, 'a');
		$this->assertEquals(true, $retval);
		$retval = $cache->save(222, $base, $id, 'b');
		$this->assertEquals(true, $retval);
		$retval = $cache->save(333, $base, $otherid, 'a');
		$this->assertEquals(true, $retval);
		$retval = $cache->save(444, $otherbase, $otherid, 'a');
		$this->assertEquals(true, $retval);

		$data = $cache->get($base, $id, 'a');
		$this->assertEquals(111, $data);
		$data = $cache->get($base, $id, 'b');
		$this->assertEquals(222, $data);
		$data = $cache->get($base, $otherid, 'a');
		$this->assertEquals(333, $data);
		$data = $cache->get($otherbase, $otherid, 'a');
		$this->assertEquals(444, $data);

		$cache->clear();

		try {
			$data = $cache->get($base, $id, 'a');
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}
		try {
			$data = $cache->get($base, $id, 'b');
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}
		try {
			$data = $cache->get($base, $otherid, 'a');
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}
		try {
			$data = $cache->get($otherbase, $otherid, 'a');
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}
	}

	public function testCleanRAM() {
		$cache = CacheRAM::singleton();
		$this->setBigClean($cache);
		$this->setBigClear($cache);
	}

	public function testCleanFS() {
		$cache = CacheFS::singleton();
		$this->setBigClean($cache);
		$this->setBigClear($cache);
	}

	public function testCleanMem() {
		if (self::$testMemoryCache) {
			$cache = CacheMemcached::singleton([['localhost', 11211]]);
			$this->setBigClean($cache);
			$this->setBigClear($cache);
		}
	}

	private function dependencies(CacheAbstract $cache) {
		$this->assertFalse($cache->appendCallback('cacheCallback')); // not in loop

		$cache->recursivestart('parent', 1);
		echo 'parent start/';
		$this->assertFalse($cache->appendCallback('nawreanaweroi')); // invalid
		$cache->appendCallback('cacheCallback');
			$cache->recursivestart('child', 2);
			echo '|child first|';
			$cache->recursiveend(false);
		echo 'parent end/';
		$cache->recursiveend(false);

		$data = $cache->get('child', 2);
		$cachedata = CacheData::unserialize($data);
		$this->assertEquals('|child first|', $cachedata->stringify($cache));
		$data = $cache->get('parent', 1);
		$cachedata = CacheData::unserialize($data);
		$this->assertEquals('parent start/aaaa|child first|parent end/', $cachedata->stringify($cache));

		$cache->delete('child', 2);
		$cache->recursivestart('child', 2);
		echo '|child second|';
		$cache->recursiveend(false);

		$data = $cache->get('child', 2);
		$cachedata = CacheData::unserialize($data);
		$this->assertEquals('|child second|', $cachedata->stringify($cache));
		$data = $cache->get('parent', 1);
		$cachedata = CacheData::unserialize($data);
		$this->assertEquals('parent start/bbbb|child second|parent end/', $cachedata->stringify($cache));
	}

	public function testDependenciesRAM() {
		$this->markTestIncomplete();
		return;
		$cache = CacheRAM::singleton();
		$this->dependencies($cache);
	}

	public function _dependencies(CacheAbstract $cache) {
		$cache->start('parent', 1);
		$cache->addDependency();
		echo 'parent start/';
		$this->assertFalse($cache->appendCallback('nawreanaweroi')); // invalid
		$cache->appendCallback('cacheCallback');
			$cache->start('child', 2);
			echo '|child first|';
			$cache->end(false);
		echo 'parent end/';
		$cache->end(false);
	}

	public function testClearRAM() {
		$cache = CacheRAM::singleton();
		$this->setBigClean($cache);
	}

	public function testClearFS() {
		$cache = CacheFS::singleton();
		$this->setBigClean($cache);
	}

	public function testClearMem() {
		if (self::$testMemoryCache) {
			$cache = CacheMemcached::singleton([['localhost', 11211]]);
			$this->setBigClean($cache);
		}
	}

	public function testClash() {
		$cache = CacheMemcached::singleton([['localhost', 11211]]);

		try {
			ob_start();
			if (!$cache->start('testClash', 0)) {
				if (!$cache->start('testClash', 0)) {
					$cache->end();
				}
				$cache->end();
			}
			$this->assertFalse(true);
		}
		catch (CacheKeyClashException $e) {
			$this->assertTrue(true);
		}
	}
}