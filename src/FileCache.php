<?php
namespace Tuatahifibre\TelflowClient;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class FileCache
{
    private $cacheFile;

    public function __construct($cacheFile)
    {
        $this->cacheFile = $cacheFile;
        
        // Create cache directory if it doesn't exist
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            error_log("Cache directory does not exist, attempting to create: " . $dir . "\n");
            if (!@mkdir($dir, 0755, true)) {
                throw new Exception("Failed to create cache directory: " . $dir);
            }

        }

        // Check directory permissions
        if (!is_writable($dir)) {
            throw new Exception("Cache directory is not writable: " . $dir);
        }
    }

    public function writeCache($response)
    {
        if (isset($response->expires_in)) {
            $date = new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland'));
            $response->expires_at = $date->modify('+' . $response->expires_in . ' seconds')->format('Y-m-d H:i:s');
            $response->refresh_expires_at = $date->modify('+'
                . ($response->refresh_expires_in)
                . ' seconds')->format('Y-m-d H:i:s');
        }

        $result = file_put_contents($this->cacheFile, json_encode($response, JSON_PRETTY_PRINT));

        if ($result === false) {
            throw new Exception("Failed to write cache file: " . $this->cacheFile);
        }
        return $result;
    }

    public function checkCache()
{
    // Check if file exists first
    if (!file_exists($this->cacheFile)) {
        return ['valid' => false, 'payload' => (object)[]];
    }

    $content = @file_get_contents($this->cacheFile);
    if (empty($content)) {
        return ['valid' => false, 'payload' => (object)[]];
    }

    try {
        $data = json_decode($content);
        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
            return ['valid' => false, 'payload' => (object)[]];
        }

        // Check if required properties exist
        if (empty($data->expires_at)) {
            return ['valid' => false, 'payload' => $data];
        }

        $now = new DateTime("now", new DateTimeZone("Pacific/Auckland"));
        $expiry = new DateTime($data->expires_at, new DateTimeZone("Pacific/Auckland"));

        if ($now < $expiry) {
            // Still valid token
            return ['valid' => true, 'payload' => $data];
        } else {
            // Token expired, but we might have a refresh token
            if (isset($data->refresh_expires_at)) {
                $refreshExpiry = new DateTime($data->refresh_expires_at, new DateTimeZone("Pacific/Auckland"));
                if ($now <= $refreshExpiry) {
                    // We have a valid refresh token
                    return ['valid' => true, 'payload' => $data];
                }
            }
            // Both tokens expired
            return ['valid' => false, 'payload' => (object)[]];
        }
    } catch (Exception $e) {
        return ['valid' => false, 'payload' => (object)[]];
    }
}
}