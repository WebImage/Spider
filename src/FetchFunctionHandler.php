<?php

namespace WebImage\Spider;

class FetchFunctionHandler implements FetchHandlerInterface
{
	/** @var callable $handler */
	private $handler;

	/**
	 * FetchFunctionHandler constructor.
	 *
	 * @param callable $handler
	 */
	public function __construct(callable $handler)
	{
		$this->handler = $handler;
	}

	/**
	 * @param FetchResponseEvent $ev
	 */
	public function handleResponse(FetchResponseEvent $ev)
	{
		call_user_func($this->handler, $ev);
	}
}