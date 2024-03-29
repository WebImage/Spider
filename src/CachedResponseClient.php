<?php

namespace WebImage\Spider;

use Goutte\Client as BaseClient;
//use GuzzleHttp\Client As BaseClient;

class CachedResponseClient extends BaseClient
{
//	public function requestAsync($method, $uri = '', array $options = [])
//	{
//
//		print_r(md5(serialize($options)));
//		print_r($options);
//		die(__FILE__.':'.__LINE__.PHP_EOL);
//		return parent::requestAsync($method, $uri, $options); // TODO: Change the autogenerated stub
//	}

	private $responseCacheFile;
	private $maxCacheAge;

	/**
	 * Cache result to specific file
	 * @param $cacheFile
	 * @param $maxCacheAge
	 */
	public function enableCache($cacheFile, $maxCacheAge)
	{
		$this->responseCacheFile = $cacheFile;
		$this->maxCacheAge = $maxCacheAge;
	}

	public function disableCache()
	{
		$this->responseCacheFile = null;
		$this->maxCacheAge = null;
	}

	protected function doRequest($request)
	{
		$response = $this->getCachedResponse();

		// Create a new request if the request was not cached
		if (null === $response) {
			$response = parent::doRequest($request);
			$this->cacheResponse($response);
		}

		return $response;
	}

	private function getCachedResponse()
	{
		// Return if caching is disabled
		if (null === $this->responseCacheFile || !file_exists($this->responseCacheFile)) return;

		// Return if cache is stale
		$cacheAge = time() - filemtime($this->responseCacheFile);
		if (null !== $this->maxCacheAge && $this->maxCacheAge > 0 && $cacheAge > $this->maxCacheAge) return;

		return unserialize(file_get_contents($this->responseCacheFile));
	}

	public function cacheResponse($response)
	{
		if (null === $this->responseCacheFile) return;

		file_put_contents($this->responseCacheFile, serialize($response));
	}
}