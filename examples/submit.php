<?php

declare(strict_types=1);

// Send a few URLs once, for example right after a page is published or deleted.

require __DIR__ . '/../vendor/autoload.php';

use Irodev\IndexNow\Client;

$client = new Client('example.com', 'your-indexnow-key');
$result = $client->submit([
    'https://example.com/blog/new-post',
    'https://example.com/blog',
]);

echo $result->status, ' ', $result->reason(), PHP_EOL;
