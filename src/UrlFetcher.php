<?php

namespace WebImage\Spider;

use Monolog\Logger;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use WebImage\Spider\FetchRequest;
use WebImage\String\Url;
use WebImage\Core\Dictionary;

//class UrlFetcher {} fetch()
//class PluggableUrlFetcher {}
// $fetcher->que


class UrlFetcher
{
	/** @var Logger */
	private Logger                $log;
	private string                $cacheDir;
	private ?CachedResponseClient $client = null;
	/** @var Dictionary<sUrl, UrlFetchStatus> */
	private Dictionary $urls;
	/**
	 * @var FetchHandlerInterface[]
	 */
	private array $onFetchHandlers = [];

//	private $onFinishedHandlers = [];

	public function __construct(string $cacheDir, Logger $log)
	{
		$this->cacheDir = rtrim($cacheDir, '/');
		$this->log      = $log;
		$this->urls     = new Dictionary();
	}

	/**
	 * @param Url $url
	 *
	 * @return null|FetchResult
	 */
	public function fetch(Url $url): ?FetchResult
	{
		$this->pushUrl($url);

		$this->log->info('Start Fetch ' . $url);
		$depth = 0;

		$response = null;

		while (count($urls = $this->getPendingUrls()) > 0) {
			$depth++;
			$this->log->info(sprintf('Round #%s - Scanning: %s', number_format($depth), number_format(count($urls))));
			$results = $this->processUrls($urls, $depth);

			// If we are on the first iteration, then grab the first response, which is the direct FetchResult of this method call
			if ($depth == 1) $response = $results[0];
		}

		$this->log->info('Finished Fetch ' . $url);

		return $response;
	}

	/**
	 * @param Dictionary $urls
	 * @param $depth
	 *
	 * @return FetchResult[]
	 */
	private function processUrls(Dictionary $urls, $depth)
	{
		/**
		 * @var string $sUrl
		 * @var FetchRequest $fetchRequest
		 **/
		$results = [];
		foreach ($urls as $sUrl => $fetchRequest) {
			$result = $this->fetchResult($fetchRequest, $depth);

			$this->dispatchOnFetchHandlers($result);
			$fetchRequest->visited(true);

			if (!$result->isCached() && $result->isCacheable()) {
				$this->cacheRequest($fetchRequest, $result->getResponse());
			}

			$urlCacheDir  = $this->getDomainCacheDir($fetchRequest->getUrl());
			$urlCacheFile = $urlCacheDir . '/urls.cache';
			file_put_contents($urlCacheFile, serialize($this->urls));

			$results[] = $result;

			if (!$result->isCached()) sleep(2);
		}

		return $results;
	}

	/**
	 * Allows a URL to be fetched without processing the request through handlers
	 * @param Url $url
	 *
	 * @return FetchResult
	 */
	public function directFetch(Url $url)
	{
		$request = new FetchRequest($url);
		$result  = $this->fetchResult($request);

		if (!$result->isCached() && $result->isCacheable()) {
			$this->cacheRequest($request, $result->getResponse());
		}

		return $result;
	}

	/**
	 * @param FetchRequest $fetchRequest
	 *
	 * @return FetchResult
	 */
	private function fetchResult(FetchRequest $fetchRequest, $depth)
	{
		$request  = null;
		$response = $this->getCachedResponse($fetchRequest);
		$crawler  = null;
		$isCached = false;

		if (null === $response) {
			$this->getClient()->request('GET', (string)$fetchRequest->getUrl());

			$client   = $this->getClient();
			$request  = $client->getRequest();
			$response = $client->getResponse();
			$crawler  = $client->getCrawler();
		} else {
			$request  = $this->createRequest($fetchRequest);
			$crawler  = $this->createCrawlerFromResponse($response);
			$isCached = true;
		}

		$this->log->info('GET ' . $request->getUri() . ' ' . $response->getStatus() . ($isCached ? ' (Cached)' : ''));

		return new FetchResult($request, $response, $crawler, $isCached, $depth);
	}

	/**
	 * @param \WebImage\Spider\FetchRequest $request
	 *
	 * @return Request
	 */
	private function createRequest(FetchRequest $request)
	{
		return new Request($request->getUrl(), 'GET');
	}

	/**
	 * @param Response $response
	 *
	 * @return Crawler
	 */
	private function createCrawlerFromResponse(Response $response)
	{
		$crawler = new Crawler();
		$crawler->addContent($response->getContent());

		return $crawler;
	}

	/**
	 * Check if a cached response exists for the request
	 *
	 * @param \WebImage\Spider\FetchRequest $request
	 *
	 * @return mixed|void
	 */
	private function getCachedResponse(FetchRequest $request)
	{
		$cacheFile   = $this->getCacheFile($request);
		$maxCacheAge = 0;

		if (null === $cacheFile || !file_exists($cacheFile)) return;

		// Return if cache is stale
		$cacheAge = time() - filemtime($cacheFile);
		if (null !== $maxCacheAge && $maxCacheAge > 0 && $cacheAge > $maxCacheAge) return;

		return unserialize(file_get_contents($cacheFile));
	}

	public function onContent($handler)
	{
		if (is_callable($handler)) $handler = new FetchFunctionHandler($handler);

		if (!($handler instanceof FetchHandlerInterface)) {
			throw new \InvalidArgumentException(sprintf('Fetch handler must implement: %s', FetchHandlerInterface::class));
		}

		$this->onFetchHandlers[] = $handler;
	}

	/**
	 * @param FetchResult $result
	 */
	private function dispatchOnFetchHandlers(FetchResult $result)
	{
		$ev                = new FetchResponseEvent($this, $result);
		$linkCacheDisabled = false;

		/** @var FetchHandlerInterface $handler */
		foreach ($this->onFetchHandlers as $handler) {
			if ($handler instanceof LoggableInterface) $handler->setLog($this->log);
			$handler->handleResponse($ev);
			$this->logIfHandlerDisabledCaching($handler, $linkCacheDisabled, $result);
			if (!$result->isCacheable()) $linkCacheDisabled = true;
		}
	}

	/**
	 * Check if the handler has disabled caching and log it
	 * @param FetchHandlerInterface $handler
	 * @param $wasDisabled
	 * @param FetchResult $result
	 */
	private function logIfHandlerDisabledCaching(FetchHandlerInterface $handler, $wasDisabled, FetchResult $result)
	{
		$did_disable = (!$wasDisabled && !$result->isCacheable());

		if ($did_disable) $this->log->info(sprintf('%s disabled caching for %s', get_class($handler), $result->getRequest()->getUri()));
	}

	/**
	 * @return CachedResponseClient
	 */
	private function getClient(): CachedResponseClient
	{
		if (null === $this->client) {
			$client = new CachedResponseClient();
			$client->followRedirects();

			$guzzleClient = new \GuzzleHttp\Client(array(
													   'curl' => array(
														   CURLOPT_SSL_VERIFYHOST => false,
														   CURLOPT_SSL_VERIFYPEER => false,
													   ),
												   ));
			$client->setClient($guzzleClient);
			$client->setHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.109 Safari/537.36');
			$this->client = $client;
		}

		return $this->client;
	}

	/**
	 * Push a URL onto the stack to be crawled
	 * @param Url $url
	 * @param integer $depth
	 * @throws \InvalidArgumentException
	 */
	public function pushUrl(Url $url, $depth = 1)
	{
		if (!is_numeric($depth)) {
			throw new \InvalidArgumentException('Depth should be numeric');
		}

		if ($this->urls->has((string)$url)) return;

		$this->log->info('Adding URL to crawl ' . $url);

		$this->urls->set((string)$url, new FetchRequest($url, $depth));
	}

	/**
	 * @return Dictionary<string url, UrlFetchStatus> Urls that have not yet been visited
	 */
	function getPendingUrls(): Dictionary
	{
		$return = new Dictionary();
		/**
		 * @var string $url
		 * @var FetchRequest $status
		 */
		foreach ($this->urls as $url => $status) {
			if (false === $status->visited()) {
				$return->set($url, $status);
			}
		}

		return $return;
	}

	function checkIfCrawlable($uri): bool
	{
		if (empty($uri) === true) {
			return false;
		}

		$stop_links = array(
			'@^javascript\:.*$@i',
			'@^#.*@',
			'@^mailto\:.*@i',
			'@^tel\:.*@i',
			'@^fax\:.*@i',
		);

		foreach ($stop_links as $pattern) {
			if (preg_match($pattern, $uri)) {
				return false;
			}
		}

		return true;
	}

	function isExternal($url, $base_url): bool
	{
		$base_url_trimmed = str_replace(array('http://', 'https://'), '', $base_url);

		return preg_match("@^http(s)?\://$base_url_trimmed@", $url) == false;
	}

	private function getDomainCacheDir(Url $url): string
	{
		$dir = $this->cacheDir . '/' . $this->getDomainCacheKey($url);
		if (!file_exists($dir)) {
			if (!mkdir($dir)) {
				throw new \RuntimeException('Unable to create cache directory: ' . $dir);
			}
		}
		return $dir;
	}

	private function getDomainCacheKey(Url $url)
	{
		$cacheKey = $url->getHost();
		return preg_replace('/[^a-zA-Z0-9\-]+/', '_', $cacheKey);
	}

	private function getCacheFile(FetchRequest $fetchRequest): string
	{
		$path_parts = explode('/', $fetchRequest->getUrl()->getPath());

		$page_part = $path_parts[count($path_parts) - 1];

		// If the page does not explicitly have an extension, then treat this path like a directory
		$page_name = null;
		if (empty($page_part) || false === strpos($page_part, '.')) {
			$page_name = '_default';
		} else {
			$page_name = array_pop($path_parts);
		}

		// @TODO Add query string to cache file string
		$dir                = $this->getDomainCacheDir($fetchRequest->getUrl());
		$download_directory = $dir . implode('/', $path_parts);

		if (!file_exists($download_directory)) {
			if (!mkdir($download_directory, 0777, true)) {
				throw new \RuntimeException('Unable to create directory: ' . $download_directory);
			}
		}

		return $download_directory . '/' . $page_name;
	}

	private function cacheRequest(FetchRequest $request, Response $response)
	{
		$cacheFile = $this->getCacheFile($request);

		file_put_contents($cacheFile, serialize($response));
	}
}