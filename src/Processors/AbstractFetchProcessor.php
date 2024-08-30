<?php

namespace WebImage\Spider\Processors;

//use WebImage\Spider\FetchFinishedEvent;
use WebImage\Spider\FetchResponseEvent;
use WebImage\Spider\FetchHandlerInterface;

//use WebImage\Spider\FetchStartEvent;

abstract class AbstractFetchProcessor implements FetchHandlerInterface
{
	abstract public function handleResponse(FetchResponseEvent $ev);

//	public function handleStart(FetchStartEvent $ev){}
//
//	public function handleFinished(FetchFinishedEvent $ev) {}
}