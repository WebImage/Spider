<?php

namespace WebImage\Spider\Processors;

use WebImage\Core\Dictionary;
use WebImage\Spider\FetchResponseEvent;
use WebImage\Spider\FetchHandlerInterface;
use WebImage\Spider\LoggableInterface;
use WebImage\Spider\LoggableTrait;
use WebImage\String\Url;

class InvalidMimeForExtensions implements FetchHandlerInterface, LoggableInterface
{
	use LoggableTrait;

	private $extMimeTypeMap;

	public function __construct(array $extensions=null)
	{
		$this->extMimeTypeMap = new Dictionary();
		$this->addExtensions($extensions === null ? $this->getDefaultExtensions() : $extensions);
	}

	protected function getDefaultExtensions()
	{
		return [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'png' => 'image/png',
			'pdf' => 'application/pdf'
		];
	}

	public function handleResponse(FetchResponseEvent $ev)
	{
		$response = $ev->getResult()->getResponse();
		$status = $response->getStatus();
		if ($status >= 400) return; // Ensure that we are only testing for "valid" responses

		$mime_type = $this->normalizeContentType($response->getHeader('Content-Type'));
		$extension = $this->getExtensionFromUrl(new Url($ev->getResult()->getRequest()->getUri()));

		if (null !== $extension) {
			$expected_mime_types = $this->extMimeTypeMap[$extension];
			if (!in_array($mime_type, $expected_mime_types)) {
				$this->getLog()->info('Invalid Content-Type (' . $mime_type . ') for ' . $ev->getResult()->getRequest()->getUri(). '.');
				$ev->getResult()->isCacheable(false);
			}
		}
	}

	private function normalizeContentType($content_type)
	{
		$parts = explode(';', $content_type);
		$content_type = array_shift($parts);

		return $content_type;
	}

	/**
	 * @param Url $url
	 * @return string|null Extension, if found, otherwise null
	 */
	private function getExtensionFromUrl(Url $url)
	{
		$path = $url->getPath();
		foreach($this->extMimeTypeMap as $ext => $mimeType) {
			$test_ext = substr($path, -(strlen($ext)+1));
			if ($test_ext == '.' . $ext) {
				return $ext;
			}
		}
	}

	/**
	 * Add multiple extension mappings
	 * @param array $extensions
	 */
	public function addExtensions(array $extensions)
	{
		foreach($extensions as $supported_extensions => $mime_type) {
			$this->addExtension($supported_extensions, $mime_type);
		}
	}

	/**
	 * * Add an extension mapping
	 * @param array|string $extensions
	 * @param array|string $mime_types
	 */
	public function addExtension($extensions, $mime_types)
	{
		if (!is_array($extensions)) $extensions = [$extensions];
		if (!is_array($mime_types)) $mime_types = [$mime_types];

		foreach($extensions as $extension) {
			$this->extMimeTypeMap->set($extension, $mime_types);
		}
	}
}