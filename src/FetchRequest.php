<?php

namespace WebImage\Spider;

use WebImage\String\Url;

class FetchRequest
{
	/**
	 * @var Url
	 */
	private $url;
	/**
	 * @var bool
	 */
	private $visited = false;

	/** @var int The depth into a crawl that this URL was found */
	private $depth = 1;
	/**
	 * @var string
	 */
	private $error;

	/**
	 * UrlStatus constructor.
	 *
	 * @param Url $url
	 */
	public function __construct(Url $url, $depth=1)
	{
		$this->url = $url;
		$this->depth = $depth;
	}

	/**
	 * @return int
	 */
	public function getDepth()
	{
		return $this->depth;
	}

	/**
	 * @return Url
	 */
	public function getUrl() { return $this->url; }

	/**
	 * Getter/setter for visited
	 * @return bool|void
	 */
	public function visited($true_false=null) {
		if (null !== $true_false) {
			$this->setVisited($true_false);
			return;
		}

		return $this->visited;
	}


	/**
	 * @param bool $true_false
	 */
	public function setVisited($true_false)
	{
		if (!is_bool($true_false)) {
			throw new \InvalidArgumentException(__METHOD__ . ' was expecting a boolean');
		}

		$this->visited = $true_false;
	}
}