<?php

namespace WebImage\Spider;

use InvalidArgumentException;
use Monolog\Logger;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use WebImage\Spider\FetchRequest;
use WebImage\String\Url;
use WebImage\Core\Dictionary;

class UrlFetcher
{
	/** @var Logger */
	private Logger                     $log;
	private string                     $cacheDir;
	private ?CachedResponseHttpBrowser $httpBrowser = null;
	/** @var Dictionary|array<string, FetchRequest> */
	private Dictionary $urls;
	/**
	 * @var FetchHandlerInterface[]
	 */
	private array $onFetchHandlers = [];

//	private $onFinishedHandlers = [];

	/**
	 * @param string $cacheDir
	 * @param Logger $log
	 * @param HttpClientInterface|null $client
	 */
	public function __construct(string $cacheDir, Logger $log, HttpClientInterface $client = null)
	{
		$this->cacheDir    = rtrim($cacheDir, '/');
		$this->log         = $log;
		$this->urls        = new Dictionary();
		$this->httpBrowser = $this->createHttpBrowser($client);
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
	private function processUrls(Dictionary $urls, $depth): array
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
	 * @param bool $cacheResponse
	 * @return FetchResult
	 */
	public function directFetch(Url $url, bool $cacheResponse = true): FetchResult
	{
		$request = new FetchRequest($url);
		$result  = $this->fetchResult($request);

		if ($cacheResponse && !$result->isCached() && $result->isCacheable()) {
			$this->cacheRequest($request, $result->getResponse());
		}

		return $result;
	}

	/**
	 * @param FetchRequest $fetchRequest
	 * @param int $depth
	 * @return FetchResult
	 */
	private function fetchResult(FetchRequest $fetchRequest, int $depth = 1): FetchResult
	{
		$request  = null;
		$response = $this->getCachedResponse($fetchRequest);
		$crawler  = null;
		$isCached = false;

		if (null === $response) {
			$this->getHttpBrowser()->request('GET', (string)$fetchRequest->getUrl());

			$client   = $this->getHttpBrowser();
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
	private function createRequest(FetchRequest $request): Request
	{
		return new Request($request->getUrl(), 'GET');
	}

	/**
	 * @param Response $response
	 *
	 * @return Crawler
	 */
	private function createCrawlerFromResponse(Response $response): Crawler
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

	/**
	 * A callback that is called each time a result is fetched
	 * @param FetchHandlerInterface $handler
	 * @return void
	 */
	public function onFetch(FetchHandlerInterface $handler): void
	{
		$this->onFetchHandlers[] = $handler;
	}

	/**
	 * Allows callables to be used as onFetch handlers
	 * @param callable $callback
	 * @return void
	 */
	public function onFetchCallback(callable $callback): void
	{
		$this->onFetch(new FetchFunctionHandler($callback));
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
	 * @return CachedResponseHttpBrowser
	 */
	private function getHttpBrowser(): CachedResponseHttpBrowser
	{
		return $this->httpBrowser;
	}

	/**
	 * Create the browser that will be used to fetch content
	 * @param HttpClientInterface|null $client
	 * @return CachedResponseHttpBrowser
	 */
	private function createHttpBrowser(?HttpClientInterface $client): CachedResponseHttpBrowser
	{
		$this->httpBrowser = new CachedResponseHttpBrowser($client ?? $this->createDefaultClient());
		$this->httpBrowser->followRedirects();

		return $this->httpBrowser;
	}

	/**
	 * Create an HttpClient that can be used by the HttpBrowser
	 * @return HttpClientInterface
	 */
	private function createDefaultClient(): HttpClientInterface
	{
		return HttpClient::create([
									  'verify_peer' => false,
									  'verify_host' => false,
									  'headers'     => [
										  'User-Agent' => 'UrlFetcher'
									  ]
								  ]);
	}

	/**
	 * Push a URL onto the stack to be crawled
	 * @param Url $url
	 * @param integer $depth
	 * @throws InvalidArgumentException
	 */
	public function pushUrl(Url $url, int $depth = 1)
	{
		if (!is_numeric($depth)) {
			throw new InvalidArgumentException('Depth should be numeric');
		}

		if ($this->urls->has((string)$url)) return;

		$this->log->info('Adding URL to crawl ' . $url);

		$this->urls->set((string)$url, new FetchRequest($url, $depth));
	}

	/**
	 * @return Dictionary<string url, UrlFetchStatus> Urls that have not yet been visited
	 */
	private function getPendingUrls(): Dictionary
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

	/**
	 * Check if a URL contains any patterns that are not crawlable, e.g. javascript, mailto, etc.
	 * @param string $uri
	 * @return bool
	 */
	private function checkIfCrawlable(string $uri): bool
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

	/**
	 * Check if a URL is external to the provided $baseUrl
	 * @param string $url
	 * @param string $baseUrl
	 * @return bool
	 */
	private function isExternal(string $url, string $baseUrl): bool
	{
		$base_url_trimmed = preg_replace('#https?://#', '', $baseUrl);

		return preg_match("@^http(s)?\://$base_url_trimmed@", $url) == false;
	}

	/**
	 * @param Url $url
	 * @return string
	 */
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

	/**
	 * Create a cache key that can be used to cache the URL domain
	 * @param Url $url
	 * @return string
	 */
	private function getDomainCacheKey(Url $url): string
	{
		$cacheKey = $url->getHost();
		return preg_replace('/[^a-zA-Z0-9\-]+/', '_', $cacheKey);
	}

	/**
	 * Get a filename that can be used for caching a URL
	 * @param \WebImage\Spider\FetchRequest $fetchRequest
	 * @return string
	 */
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

	/**
	 * Cache a request
	 * @param \WebImage\Spider\FetchRequest $request
	 * @param Response $response
	 * @return void
	 */
	private function cacheRequest(FetchRequest $request, Response $response): void
	{
		$cacheFile = $this->getCacheFile($request);

		file_put_contents($cacheFile, serialize($response));
	}
}