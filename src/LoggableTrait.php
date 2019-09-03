<?php

namespace WebImage\Spider;

use Monolog\Logger;

trait LoggableTrait {
	private $log;
	/**
	 * @return Logger
	 */
	public function getLog()
	{
		return $this->log;
	}

	/**
	 * @param Logger $logger
	 */
	public function setLog(Logger $logger)
	{
		$this->log = $logger;
	}
}