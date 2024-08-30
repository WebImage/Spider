<?php

namespace WebImage\Spider;

use Monolog\Logger;

trait LoggableTrait
{
	private Logger $log;

	/**
	 * @return Logger
	 */
	public function getLog(): Logger
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