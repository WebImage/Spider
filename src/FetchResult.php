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
	private $request;
	/**
	 * @var Response
	 */
	private $response;
	/**
	 * @var Crawler
	 */
	private $crawler;
	/**
	 * @var bool
	 */
	private $isCached = false;
	/**
	 * @var bool
	 */
	private $isCacheable = true;
	/**
	 * @var int
	 */
	private $depth = 1;
	/**
	 * FetchResponse constructor.
	 *
	 * @param Response $response
	 */
	public function __construct(Request $request, Response $response, Crawler $crawler, $isCached=false, $depth=1)
	{
		$this->request = $request;
		$this->response = $response;
		$this->crawler = $crawler;
		$this->isCached = $isCached;
		$this->depth = intval($depth);
	}

	/**
	 * @return Request
	 */
	public function getRequest()
	{
		return $this->request;
	}

	/**
	 * @return Response
	 */
	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * @return Crawler
	 */
	public function getCrawler()
	{
		return $this->crawler;
	}

	public function isCached()
	{
		return $this->isCached;
	}

	/**
	 * @return int
	 */
	public function getDepth()
	{
		return $this->depth;
	}

	public function isCacheable($true_false=null) {
		if (null !== $true_false && !is_bool($true_false)) {
			throw new \InvalidArgumentException(__METHOD__ . ' was expecting a boolean');
		}

		if (null === $true_false) {
			return $this->isCacheable;
		} else if (is_bool($true_false)) {
			$this->isCacheable = $true_false;
		}
	}
}