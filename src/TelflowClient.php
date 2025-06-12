<?php

namespace Tuatahifibre\TelflowClient;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

class TelflowClient
{
    // Fix endpoint paths with correct telflow prefix
    const TELFLOW_ACCESS_TOKEN_URL = '/auth/realms/telflow/protocol/openid-connect/token';
    const TELFLOW_ORDER_ENDPOINT = '/api/v2/productOrder';

    private $delay;
    private $attempts;
    private $max_requests;
    private $root_url;
    private $client_id;
    private $client_secret;
    public $username;
    public $password;
    public $token;
    private $curl_handle;
    private $cacheFile;
    private $cache;

    /**
     * @var integer Maximum number of retry attempts
     */
    private $maxRetries = 3;

    /**
     * @var integer Delay between retries in milliseconds
     */
    private $retryDelay = 1000;

    /**
     * Constructor
     * @param HttpRequestInterface|null $curl_handle Optional curl handle/mock
     * @param string|null $cacheFile Optional path to cache file
     */
    public function __construct($curl_handle = null, $cacheFile = null)
    {
        $this->curl_handle = $curl_handle;
        $this->cacheFile = $cacheFile;

        if ($this->cacheFile && file_exists($this->cacheFile)) {
            $contents = file_get_contents($this->cacheFile);
            if ($contents !== false) {
                $this->cache = json_decode($contents);
            }
        }
    }

    /**
     * Configure retry behavior
     * @param integer $retries
     * @return TelflowClient
     */
    public function setMaxRetries($retries)
    {
        $this->maxRetries = (int)$retries;
        return $this;
    }

    /**
     * Set delay between retries in milliseconds
     * @param integer $milliseconds
     * @return TelflowClient
     */
    public function setRetryDelay($milliseconds)
    {
        $this->retryDelay = (int)$milliseconds;
        return $this;
    }

    /**
     * Get configured curl handle
     * @return HttpRequestInterface
     */
    private function getCurlHandle()
    {
        if (!$this->curl_handle instanceof HttpRequestInterface) {
            $this->curl_handle = new CurlRequest();
            $this->curl_handle->setMaxRetries($this->maxRetries)
                ->setRetryDelay($this->retryDelay);
        }

        return $this->curl_handle;
    }



    /**
     * Get access token with retry support
     * @param string $type
     * @return TelflowHttpResponse
     * @throws TelflowClientAuthException
     */
    private function getAccessToken($type)
    {
        $c = null;
        try {
            switch ($type) {
                case "auth":
                    $header = $this->createAuthHeader();
                    $c = $this->getCurlHandle();
                    $c->setUrl($this->buildUrl(self::TELFLOW_ACCESS_TOKEN_URL));

                    // Build form data with client credentials
                    $postData = http_build_query(array(
                        'grant_type' => 'password',
                        'username' => $this->username,
                        'password' => $this->password,
                        'client_id' => $this->client_id,
                        'client_secret' => $this->client_secret
                    ));


                    $c->setOption(CURLOPT_HTTPHEADER, $header)
                        ->setOption(CURLOPT_RETURNTRANSFER, true)
                        ->setOption(CURLOPT_POST, true)
                        ->setOption(CURLOPT_POSTFIELDS, $postData);

                    $response = $c->execute();
                    $response_code = $c->getInfo(CURLINFO_RESPONSE_CODE);
                    $content_type = $c->getInfo(CURLINFO_CONTENT_TYPE);

                    $httpResponse = new TelflowHttpResponse($response_code, $content_type, $response);

                    // Store token data
                    if ($response_code === 200) {

                        // Update token cache
                        $this->updateTokenCache($httpResponse->body());
                    }

                    return $httpResponse;

                case "refresh":
                    if (!isset($this->token) || !isset($this->token->refresh_token)) {
                        throw new TelflowClientAuthException("No refresh token available", 401);
                    }

                    $header = $this->createAuthHeader();
                    $c = $this->getCurlHandle();
                    $c->setUrl($this->buildUrl(self::TELFLOW_ACCESS_TOKEN_URL));
                    $c->setOption(CURLOPT_HTTPHEADER, $header)
                        ->setOption(CURLOPT_RETURNTRANSFER, true)
                        ->setOption(CURLOPT_POST, true)
                        ->setOption(CURLOPT_POSTFIELDS, "grant_type=refresh_token&refresh_token={$this->token->refresh_token}");

                    $response = $c->execute();
                    $response_code = $c->getInfo(CURLINFO_RESPONSE_CODE);
                    $content_type = $c->getInfo(CURLINFO_CONTENT_TYPE);

                    $httpResponse = new TelflowHttpResponse($response_code, $content_type, $response);


                    if ($response_code == 200) {
                        $this->updateTokenCache($httpResponse->body());
                    }
                    break;

                default:
                    throw new TelflowClientAuthException("Invalid token request type", 400);
            }

            try {
                $response = $c->execute();
                $response_code = $c->getInfo(CURLINFO_RESPONSE_CODE);
                $content_type = $c->getInfo(CURLINFO_CONTENT_TYPE);

                $httpResponse = new TelflowHttpResponse($response_code, $content_type, $response);

                // Update token cache if successful
                if ($response_code === 200) {
                    $this->updateTokenCache($httpResponse->body());
                }

                return $httpResponse;

            } catch (TelflowClientException $e) {
                $error_desc = '';
                if (isset($response)) {
                    $json = json_decode($response);
                    if ($json && isset($json->error_description)) {
                        $error_desc = $json->error_description;
                    }
                }
                if (empty($error_desc)) {
                    $error_desc = $e->getMessage();
                }
                if (!isset($response_code)) {
                    $response_code = $e->getCode();
                }
                throw new TelflowClientAuthException($error_desc, $response_code);
            }

        } finally {
            if (isset($c)) {
                $c->close();
            }
        }
    }

    /**
     * Update token cache and write to file if configured
     * @param object $token
     */
    private function updateTokenCache($token)
    {
        $this->token = $token;

        // Add expires_at timestamps
        if (isset($token->expires_in)) {
            $date = new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland'));
            $this->token->expires_at = $date->modify('+' . $token->expires_in . ' seconds')->format('Y-m-d H:i:s');
            $this->token->refresh_expires_at = $date->modify('+' . ($token->refresh_expires_in) . ' seconds')->format('Y-m-d H:i:s');
        }

        // Update cache object
        $this->cache = $this->token;

        // Write to cache file if path is set
        if (!empty($this->cacheFile)) {
            $cacheDir = dirname($this->cacheFile);

            // Ensure cache directory exists
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            // Write cache file
            if (file_put_contents($this->cacheFile, json_encode($this->token)) === false) {
                error_log(sprintf("Failed to write cache file: %s", $this->cacheFile));
            }
        }
    }


    /**
     * Check if token needs refresh
     * @throws TelflowClientAuthException
     */
    public function checkToken()
    {
        if (!isset($this->token)) {
            if (isset($this->cache)) {
                $this->token = $this->cache;
            } else {
                $this->getAccessToken("auth");
                return;
            }
        }

        if (isset($this->token->expires_at)) {
            $expires = new DateTimeImmutable($this->token->expires_at, new DateTimeZone('Pacific/Auckland'));
            $refresh_expires = new DateTimeImmutable($this->token->refresh_expires_at, new DateTimeZone('Pacific/Auckland'));
            $now = new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland'));

            if ($expires <= $now && $refresh_expires < $now) {
                $this->getAccessToken("refresh");
            } else if ($expires <= $now) {
                // If access_token is expired and refresh token is no longer valid, re-authenticate
                $this->getAccessToken("auth");
            }
        }
    }

    /**
     * Get PIID with retry support
     * @param string $order_no
     * @return TelflowHttpResponse
     * @throws TelflowClientException
     */
    public function getPIID($order_no)
    {
        $parameters = array('id' => $order_no);
        $headers = array(
            "Accept-Language: en-US",
            "Cache-Control: max-age=0",
            "Connection: keep-alive",
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->token->access_token
        );

        $this->checkToken();
        $c = $this->getCurlHandle();

        try {
            $c->setUrl($this->buildUrl(self::TELFLOW_ORDER_ENDPOINT, true) . "?" . http_build_query($parameters));
            $c->setOption(CURLOPT_HTTPHEADER, $headers)
                ->setOption(CURLOPT_CUSTOMREQUEST, 'GET')
                ->setOption(CURLOPT_RETURNTRANSFER, true);

            $response = $c->execute();
            $response_code = $c->getInfo(CURLINFO_RESPONSE_CODE);
            $content_type = $c->getInfo(CURLINFO_CONTENT_TYPE);

            // Check for HTML response which indicates a routing/server error
            if (strpos($content_type, 'text/html') !== false) {
                throw new TelflowClientException(
                    "Invalid server response - check API endpoint configuration",
                    $response_code
                );
            }

            $httpResponse = null;

            if ($response !== false) {
                $httpResponse = new TelflowHttpResponse($response_code, $content_type, $response);
            }

            if ($response_code === 200 && $httpResponse != null) {
                $body = $httpResponse->body();
                if (!empty($body->customerOrders) &&
                    isset($body->customerOrders[0]->orderItem[0]->product[0]->id))
                {
                    $httpResponse->setBody($body->customerOrders[0]->orderItem[0]->product[0]->id);
                    return $httpResponse;
                }
            }

            // If we get here, no PIID was found
            throw new TelflowClientException(
                "Unable to find PIID for input order",
                404
            );
        } finally {
            $c->close();
        }
    }

    /**
     * Create authentication header for token requests
     * @return array
     * @throws TelflowClientAuthException
     */
    private function createAuthHeader()
    {
        if (empty($this->client_id) || empty($this->client_secret)) {
            throw new TelflowClientAuthException('Client credentials not set', 401);
        }

        // For token requests, we need form-urlencoded content type
        return array(
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        );
    }

    /**
     * Build complete URL for API requests
     * @param string $endpoint API endpoint path
     * @param boolean $requiresAuth Whether endpoint requires authentication
     * @return string Full URL
     * @throws TelflowClientAuthException
     */
    private function buildUrl($endpoint, $requiresAuth = false)
    {
        if (empty($this->root_url)) {
            throw new TelflowClientAuthException('Base URL not set', 400);
        }

        if ($requiresAuth && !isset($this->token)) {
            throw new TelflowClientAuthException('Not authenticated', 401);
        }

        // Clean up URL parts and ensure proper prefixes
        $base = rtrim($this->root_url, '/');
        $path = ltrim($endpoint, '/');

        $url = $base . '/' . $path;

        return $url;
    }

    /** Setters */
    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function setBaseUrl($url)
    {
        $this->root_url = $url;
        return $this;
    }

    public function setClientId($client_id)
    {
        $this->client_id = $client_id;
        return $this;
    }

    public function setClientSecret($client_secret)
    {
        $this->client_secret = $client_secret;
        return $this;
    }
}