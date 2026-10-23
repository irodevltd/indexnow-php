<?php

declare(strict_types=1);

// Create an IndexNow key and its key file in your document root:
//   php examples/create-key.php /var/www/example.com/public
// Keep the printed key in your config; the file must stay reachable at https://<your-host>/<key>.txt.

require __DIR__ . '/../vendor/autoload.php';

use Irodev\IndexNow\Key;

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Usage: php create-key.php <document root>\n");
    exit(1);
}
$key = Key::generate();
echo 'Key:  ', $key, PHP_EOL;
echo 'File: ', Key::writeFile($root, $key), PHP_EOL;
