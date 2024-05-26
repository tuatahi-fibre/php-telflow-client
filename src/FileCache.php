<?php

namespace Tuatahifibre\TelflowClient;

use DateTime;
use DateTimeZone;

class FileCache
{
    private $cacheFile;

    public function __construct($cacheFile)
    {
        $this->cacheFile = $cacheFile;
    }

    public function writeCache($response)
    {
        file_put_contents($this->cacheFile, json_encode($response, JSON_PRETTY_PRINT));
    }

    public function checkCache()
    {
        if (@file_get_contents($this->cacheFile)) {
            try {
                $data = json_decode(file_get_contents($this->cacheFile));
                if ($data->expires_at != "") {
                    $now = new DateTime("", new DateTimeZone("Pacific/Auckland"));
                    $expiry = new DateTime("$data->expires_at", new DateTimeZone("Pacific/Auckland"));

                    if ($now < $expiry) {
                        // Still valid token ;)
                        return ['valid' => true, 'payload' => $data];

                    } elseif ($data->refresh_by <= $now) {
                        // We have a refresh token.
                        return ['valid' => false, 'payload' => $data];
                    }
    //                return ['valid' => false, 'payload' => ''];
                }
            }
            catch (Exception $e)
            {
                echo "Caught exception: ", $e->getMessage(), "\n";
            }
        }
        else {
            return ['valid' => false, 'payload' => ''];
        }

    }
}