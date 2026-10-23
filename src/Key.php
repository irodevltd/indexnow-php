<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

/**
 * The IndexNow key: 8 to 128 characters of a–z, A–Z, 0–9 and "-". It is public by design. Search engines check it
 * against a text file at your site root, <key>.txt, that holds the key and nothing else.
 */
final class Key
{
    /** A new random key of 32 hexadecimal characters. */
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function isValid(string $key): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9-]{8,128}$/', $key);
    }

    /** The key file's name at the site root. */
    public static function fileName(string $key): string
    {
        return $key . '.txt';
    }

    /** Write the key file into the document root and return its path. */
    public static function writeFile(string $documentRoot, string $key): string
    {
        if (!self::isValid($key)) {
            throw new \InvalidArgumentException('An IndexNow key is 8–128 characters of a–z, A–Z, 0–9 and "-".');
        }
        $path = rtrim($documentRoot, '/\\') . DIRECTORY_SEPARATOR . self::fileName($key);
        if (file_put_contents($path, $key) === false) {
            throw new \RuntimeException('Could not write ' . $path);
        }
        return $path;
    }

    /** Is the key file in the document root, holding the key? Without it every notice is refused (HTTP 403). */
    public static function fileMatches(string $documentRoot, string $key): bool
    {
        $path = rtrim($documentRoot, '/\\') . DIRECTORY_SEPARATOR . self::fileName($key);
        return is_file($path) && trim((string) file_get_contents($path)) === $key;
    }
}
