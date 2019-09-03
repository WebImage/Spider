<?php

namespace WebImage\Spider;

use Goutte\Client;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use WebImage\String\Url;

class FetchResponseEvent {
	/**
	 * @var UrlFetcher
	 */
	private $target;
	/**
	 * @var FetchResult
	 */
	private $result;
	/**
	 * FetchEvent constructor.
	 *
	 * @param UrlFetcher $target
	 * @param FetchResult $result
	 */
	public function __construct(UrlFetcher $target, FetchResult $result)
	{
		$this->target = $target;
		$this->result = $result;
	}

	/**
	 * @return UrlFetcher
	 */
	public function getTarget()
	{
		return $this->target;
	}

	/**
	 * @return FetchResult
	 */
	public function getResult()
	{
		return $this->result;
	}
	/**
	 * @return Crawler
	 */
//	public function getCrawler()
//	{
//		return $this->crawler;
//	}
}