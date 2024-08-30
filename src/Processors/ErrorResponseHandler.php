<?php

namespace WebImage\Spider\Processors;

use WebImage\Spider\FetchResponseEvent;
use WebImage\Spider\FetchHandlerInterface;
use WebImage\Spider\LoggableInterface;
use WebImage\Spider\LoggableTrait;

class ErrorResponseHandler implements FetchHandlerInterface, LoggableInterface
{
	use LoggableTrait;

	public function handleResponse(FetchResponseEvent $ev)
	{
		$response = $ev->getResult()->getResponse();
		if ($response->getStatusCode() >= 400) {
			$this->getLog()->info('Bad Status: ' . $response->getStatusCode() . '; ' . $ev->getUrl());
		}
	}
}