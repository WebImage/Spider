<?php

namespace WebImage\Spider;

interface FetchHandlerInterface
{
	public function handleResponse(FetchResponseEvent $ev);
//	public function handleStart(FetchStartEvent $ev);
//	public function handleFinished(FetchFinishedEvent $ev);
}