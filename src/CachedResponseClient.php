<?php

namespace WebImage\Spider;

use Symfony\Component\BrowserKit\Response;
use Symfony\Component\BrowserKit\HttpBrowser;

class CachedResponseClient extends HttpBrowser
{
	private ?string $responseCacheFile;
	private ?int    $maxCacheAge;

	/**
	 * Cache result to specific file
	 * @param string $cacheFile
	 * @param int $maxCacheAge
	 */
	public function enableCache(string $cacheFile, int $maxCacheAge)
	{
		$this->responseCacheFile = $cacheFile;
		$this->maxCacheAge       = $maxCacheAge;
	}

	public function disableCache()
	{
		$this->responseCacheFile = null;
		$this->maxCacheAge       = null;
	}

	protected function doRequest(object $request): Response
	{
		$response = $this->getCachedResponse();

		// Create a new request if the request was not cached
		if ($response === null) {
			$response = parent::doRequest($request);
			$this->cacheResponse($response);
		}

		return $response;
	}

	/**
	 * Get a cached version of the request, if available
	 * @return Response|null
	 */
	private function getCachedResponse(): ?Response
	{
		// Return if caching is disabled
		if (null === $this->responseCacheFile || !file_exists($this->responseCacheFile)) return null;

		// Return if cache is stale
		$cacheAge = time() - filemtime($this->responseCacheFile);
		if (null !== $this->maxCacheAge && $this->maxCacheAge > 0 && $cacheAge > $this->maxCacheAge) return null;

		return unserialize(file_get_contents($this->responseCacheFile));
	}

	public function cacheResponse(Response $response): void
	{
		if (null === $this->responseCacheFile) return;

		file_put_contents($this->responseCacheFile, serialize($response));
	}
}