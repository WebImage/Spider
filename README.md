# Spider

A wrapper for Symfony/Browser-Kit that allows a URL to be downloaded, cached, and crawled.

## Usage

```php
use WebImage\Spider\UrlFetcher;
use Symfony\Component\HttpClient\HttpClient;
$logger = new \Monolog\Logger('spider');
$fetcher = new UrlFetcher('/path/to/cache', $logger, HttpClient::create());
$result = $fetcher->fetch(new Url('https://www.domain.com'));
```
It's a good idea to create an HttpClient with a user agent, for example 
```php
use Symfony\Component\HttpClient\HttpClient;
HttpClient::create([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'
    ]
]);
```

A crawler can be setup to recursively crawl URLs by setting up onFetch(FetchHandlerInterface) or onFetchCallback listeners.

```php
use WebImage\Spider\FetchResponseEvent;
/** @var \WebImage\Spider\UrlFetcher $fetcher */
$fetcher->onFetchCallback(function(FetchResponseEvent $ev) {
    // Perform some logic here, then
    $ev->getTarget()->fetch(new Url('https://www.another.com/path'));
});
```

Add onFetch(...) and onFetchCallback(...) pushes the URL onto a stack that is recursively processed in the order that URLs are added.

