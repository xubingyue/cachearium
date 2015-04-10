<?php
/**
 * Corollarium Tecnologia Ltda.
 * Copyright (c) 2008-2014 Corollarium Tecnologia Ltda.
 */

require_once(__DIR__ . '/../Cache.php');

class CacheBasicTest extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = false;

	static public function setUpBeforeClass() {
		CacheMemcached::singleton([['localhost', 11211]]); // init server
		CacheAbstract::clearAll();
	}

	static public function tearDownAfterClass() {
	}

	public function testFactory() {
		try {
			CacheAbstract::factory("invalidbackend");
			$this->assertTrue(false);
		}
		catch (CacheInvalidBackendException $e) {
			$this->assertTrue(true);
		}
	}

	private function setGetClean(CacheAbstract $cache) {
		$base = 'base';

		// enable
		$this->assertTrue($cache->isEnabled());
		$cache->enable(false);
		$this->assertFalse($cache->isEnabled());
		$cache->enable(true);
		$this->assertTrue($cache->isEnabled());
		$cache->disable();
		$this->assertFalse($cache->isEnabled());
		$cache->enable(true);
		$this->assertTrue($cache->isEnabled());

		$cache->setDefaultLifetime(3600);
		$this->assertEquals(3600, $cache->getDefaultLifetime());

		$key1 = new CacheKey($base, 1);
		$cache->cleanK($key1);

		try {
			$data = $cache->getK($key1);
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}

		$retval = $cache->storeK(234, $key1);
		$this->assertTrue($retval);

		try {
			$data = $cache->getK($key1);
			$this->assertEquals(234, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		// sleep(1);

		try {
			$data = $cache->getK($key1);
			$this->assertEquals(234, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		$cache->cleanK($key1);
		try {
			$data = $cache->getK($key1);
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}

		$key2 = new CacheKey($base, 2, 'a');
		$key3 = new CacheKey($base, 3, 'a');
		// now change again and delete
		$retval = $cache->storeK(234, $key2);
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->getK($key2);
			$this->assertEquals(234, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->deleteK($key2));

		// test null
		$retval = $cache->storeK(null, $key3);
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->getK($key3);
			$this->assertEquals(null, $data);
		}
		catch (NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->deleteK($key3));

		$this->assertArrayHasKey(CacheLogEnum::ACCESSED, $cache->getLogSummary());
		$this->assertGreaterThan(0, $cache->getLogSummary()[CacheLogEnum::ACCESSED]);
	}

	public function testSetGetCleanRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->setGetClean($cache);
		}
	}

	public function testSetGetCleanFS() {
		$cache = CacheFS::singleton();
		if ($cache->isEnabled()) {
			$this->setGetClean($cache);
		}
	}

	public function testSetGetCleanMemcached() {
		$cache = CacheMemcached::singleton([['localhost', 11211]]);
		if ($cache->isEnabled()) {
			$this->setGetClean($cache);
		}
	}

	private function getStoreData(CacheAbstract $cache) {
		$base = 'base';

		$this->assertTrue($cache->isEnabled());
		$cache->setDefaultLifetime(3600);
		$this->assertEquals(3600, $cache->getDefaultLifetime());

		// clean
		$key1 = new CacheKey($base, 1);
		$cache->cleanK($key1);

		// nothing there
		try {
			$data = $cache->getK($key1);
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}

		// store
		$cd = new CacheData(234, $key1);
		$retval = $cache->storeData($cd);
		$this->assertTrue($retval);

		// get
		try {
			$data = $cache->getDataK($key1);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(234, $data->getFirstData());
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		sleep(1);

		try {
			$data = $cache->getDataK($key1);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(234, $data->getFirstData());
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		// clean
		$cache->cleanK($key1);
		try {
			$data = $cache->getDataK($key1);
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}

		// check conflicts
		$key2 = new CacheKey($base, 2, 'a');
		$key3 = new CacheKey($base, 3, 'a');
		// now change again and delete
		$retval = $cache->storeData(new CacheData(234, $key2));
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->getDataK($key2);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(234, $data->getFirstData());
		}
		catch (NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->deleteK($key2));

		// test null
		$retval = $cache->storeData(new CacheData(null, $key3));
		$this->assertEquals(true, $retval);
		try {
			$data = $cache->getDataK($key3);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals(null, $data->getFirstData());
		}
		catch (NotCachedException $e) {
			$this->fail();
		}
		$this->assertTrue($cache->deleteK($key3));
	}

	public function testgetStoreDataRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->getStoreData($cache);
		}
	}

	public function testgetStoreDataMemcached() {
		$cache = CacheMemcached::singleton([['localhost', 11211]]);
		if ($cache->isEnabled()) {
			$this->getStoreData($cache);
		}
	}

	public function testgetStoreDataFS() {
		$cache = CacheFS::singleton();
		if ($cache->isEnabled()) {
			$this->getStoreData($cache);
		}
	}

	private function dependency(CacheAbstract $cache) {
		// store
		$key1 = new CacheKey('Namespace', 'Subname');
		$cd = new CacheData('xxxx', $key1);
		$depkey = new CacheKey('Namespace', 'SomeDep');
		$cd->addDependency($depkey);
		$cache->storeData($cd);

		// check if it is cached
		try {
			$data = $cache->getDataK($key1);
			$this->assertInstanceOf('CacheData', $data);
			$this->assertEquals('xxxx', $data->getFirstData());
		}
		catch (NotCachedException $e) {
			$this->fail();
		}

		// invalidate a dependency
		$cache->invalidate($depkey);

		// get the original and it should be uncached
		try {
			$data = $cache->getDataK($key1);
			$this->fail();
		}
		catch (NotCachedException $e) {
			$this->assertTrue(true);
		}
	}

	public function testdependencyRAM() {
		$cache = CacheRAM::singleton();
		if ($cache->isEnabled()) {
			$this->dependency($cache);
		}
	}

	public function testdependencyMemcached() {
		$cache = CacheMemcached::singleton([['localhost', 11211]]);
		if ($cache->isEnabled()) {
			$this->dependency($cache);
		}
	}

	public function testdependencyFS() {
		$this->markTestSkipped();
		$cache = CacheFS::singleton();
		if ($cache->isEnabled()) {
			$this->dependency($cache);
		}
	}
}