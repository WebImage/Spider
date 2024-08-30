<?php

namespace WebImage\Spider\Processors;

use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Component\DomCrawler\Crawler;
use WebImage\Core\Dictionary;
use WebImage\Spider\FetchResponseEvent;
use WebImage\Spider\FetchHandlerInterface;
use WebImage\Spider\FetchResult;
use WebImage\Spider\LoggableInterface;
use WebImage\Spider\LoggableTrait;
use WebImage\String\Url;

/**
 * Can be used to cause PageFetcher to find all links on
 * the seed URL and recursively crawl the found link pages
 */
class AutoCrawl implements FetchHandlerInterface, LoggableInterface
{
	use LoggableTrait;

	/**
	 * @var int The maximum depth that the crawler should crawl
	 */
	private int $maxDepth;
	/**
	 * @var bool Whether the crawler should add external domains to the crawl stack
	 */
	private bool $shouldCrawlExternalDomains = false;

	public array $linkPaths = [];

	/**
	 * AutoCrawl constructor.
	 *
	 * @param int $maxDepth
	 */
	public function __construct(int $maxDepth = 3)
	{
		$this->maxDepth = $maxDepth;
	}

	public function handleResponse(FetchResponseEvent $ev)
	{
		$result = $ev->getResult();
		$links  = $this->getCrawlableLinks($result->getRequest(), $result->getResponse(), $result->getCrawler());
		$this->linkPaths((string)$ev->getResult()->getRequest()->getUri(), $links);
		foreach ($links as $link) {
			if ($result->getDepth() <= $this->maxDepth) {
				$ev->getTarget()->pushUrl($link);
			} else {
				$this->getLog()->info('Not crawling ' . $link . ' (' . $result->getDepth() . ') because max depth (' . $this->maxDepth . ') reached');
			}
		}
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 * @param Crawler $crawler
	 *
	 * @return Url[]
	 */
	private function getCrawlableLinks(Request $request, Response $response, Crawler $crawler): array
	{
		$url = new Url($request->getUri());

		if (!$this->isValidResponse($response)) return;

		$links = $crawler->filter('a')->each(function (DomCrawler $node, $i) use ($url) {
			$node_text = trim($node->text());
			$href      = $node->attr('href');

			$href_url = $this->normalizedUrlFromHref($url, $href);

			if (
				null === $href_url ||
				(!$this->shouldCrawlExternalDomains && $href_url->getHost() != $url->getHost())
			) {
				return null;
			}

			return $href_url;
		});

		return array_filter($links); // Remove NULL values
	}

	private function isValidResponse(Response $response)
	{
		$content_type = $response->getHeader('Content-Type');
		$is_html      = (false !== strpos($content_type, 'text/html'));

		return (
			$response->getStatusCode() == 200 &&
			$is_html
		);
	}

	private function normalizedUrlFromHref(Url $current_url, $target_href): ?Url
	{
		$href = preg_replace('@#.*$@', '', $target_href); // remove HASH
		if (empty($href) || !$this->isLinkCrawlable($href)) return null;

		$url = new Url($href);

		$path      = $url->getPath();
		$link_type = 'Unknown';

		// Ensure path is set to something
		if (empty($path)) $path = '/';

		if (strlen($url->getHost()) == 0) {

			$url->setScheme($current_url->getScheme());
			$url->setHost($current_url->getHost());

			// Handle relative links
			if (substr($path, 0, 1) != '/') {
				$parts = explode('/', $current_url->getPath());

				// Remove the last path element if it is empty
				$last_part = $parts[count($parts) - 1];
				if (empty($last_part)) array_pop($parts);

				$parts[] = $path;
				$path    = implode('/', $parts);

				$link_type = 'Relative';
			}
		}

		$url->setPath($path);

		$link_type = $link_type ?: ($url->getHost() == $current_url->getHost() ? 'Absolute' : 'External');

		$this->getLog()->debug($current_url . ' - Normalized link: ' . $target_href . ' => ' . $url . ' (' . $link_type . ')');

		return $url;
	}

	/**
	 * Checks whether a link can be crawled (e.g. do not include Javascript, email, tel, fax links, etc.)
	 * @param string $link
	 *
	 * @return bool
	 */
	private function isLinkCrawlable(string $link): bool
	{
		if (empty($link) === true) {
			return false;
		}

		// Do not include any links with a scheme specified, unless it is HTTP
		if (preg_match('/^(.+):(.+)/', $link, $matches)) {
			if (!in_array($matches[1], ['http', 'https'])) return false;
		}

		return true;
	}

	/**
	 * @param string $url
	 * @param Url[] $links
	 */
	private function linkPaths(string $url, array $links)
	{
		if (!isset($this->linkPaths[$url])) $this->linkPaths[$url] = [];

		foreach ($links as $link) {
			$this->linkPaths[$url][] = (string)$link;
		}
	}

	public function getLinkPaths(): array
	{
		return $this->linkPaths;
	}
}