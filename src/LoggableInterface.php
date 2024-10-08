<?php

namespace WebImage\Spider;

use Monolog\Logger;

interface LoggableInterface
{
	/**
	 * @return Logger
	 */
	public function getLog(): Logger;

	/**
	 * @param Logger $logger
	 */
	public function setLog(Logger $logger);
}