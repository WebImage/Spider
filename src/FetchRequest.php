<?php

namespace WebImage\Spider;

use WebImage\String\Url;

class FetchRequest
{
	/**
	 * @var Url
	 */
	private Url $url;
	/**
	 * @var bool
	 */
	private bool $visited = false;

	/** @var int The depth into a crawl that this URL was found */
	private int $depth = 1;
	/**
	 * @var string
	 */
	private string $error;

	/**
	 * UrlStatus constructor.
	 *
	 * @param Url $url
	 */
	public function __construct(Url $url, int $depth = 1)
	{
		$this->url   = $url;
		$this->depth = $depth;
	}

	/**
	 * @return int
	 */
	public function getDepth(): int
	{
		return $this->depth;
	}

	/**
	 * @return Url
	 */
	public function getUrl(): Url
	{
		return $this->url;
	}

	/**
	 * Getter/setter for visited
	 * @return bool|void
	 */
	public function visited($true_false = null)
	{
		if (null !== $true_false) {
			$this->setVisited($true_false);
			return;
		}

		return $this->visited;
	}


	/**
	 * @param bool $true_false
	 */
	public function setVisited(bool $true_false)
	{
		$this->visited = $true_false;
	}
}