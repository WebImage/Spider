<?php

namespace WebImage\Spider;

use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use WebImage\String\Url;

class FetchResult
{
	/**
	 * @var Request
	 */
	private Request $request;
	/**
	 * @var Response
	 */
	private Response $response;
	/**
	 * @var Crawler
	 */
	private Crawler $crawler;
	/**
	 * @var bool
	 */
	private bool $isCached = false;
	/**
	 * @var bool
	 */
	private bool $isCacheable = true;
	/**
	 * @var int
	 */
	private int $depth = 1;

	/**
	 * FetchResponse constructor.
	 *
	 * @param Request $request
	 * @param Response $response
	 * @param Crawler $crawler
	 * @param bool $isCached
	 * @param int $depth
	 */
	public function __construct(Request $request, Response $response, Crawler $crawler, bool $isCached = false, int $depth = 1)
	{
		$this->request  = $request;
		$this->response = $response;
		$this->crawler  = $crawler;
		$this->isCached = $isCached;
		$this->depth    = intval($depth);
	}

	/**
	 * @return Request
	 */
	public function getRequest(): Request
	{
		return $this->request;
	}

	/**
	 * @return Response
	 */
	public function getResponse(): Response
	{
		return $this->response;
	}

	/**
	 * @return Crawler
	 */
	public function getCrawler(): Crawler
	{
		return $this->crawler;
	}

	public function isCached(): bool
	{
		return $this->isCached;
	}

	/**
	 * @return int
	 */
	public function getDepth(): int
	{
		return $this->depth;
	}

	public function isCacheable(): bool
	{
		return $this->isCacheable;
	}

	public function setCacheable(bool $isCacheable): void
	{
		$this->isCacheable = $isCacheable;
	}
}