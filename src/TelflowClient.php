<?php

namespace Tuatahifibre\TelflowClient;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

class TelflowClient
{
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

    /**
     * @var string
     * The location which we want to store the cache file
     * Set by default to sprintf("%s/api-cred-cache.json", getcwd())
     */
    private $cacheFile;

    /**
     * @var FileCache
     * This is for storing the FileCache object consumed by this class.
     */
    private $cache;
    /**
     *
     */
    const TELFLOW_ACCESS_TOKEN_URL = "auth/realms/telflow/protocol/openid-connect/token";
    /**
     *
     */
    const TELFLOW_API_VERSION = "api/v2";
    /**
     *
     */
    const TELFLOW_ORDER_ENDPOINT = "productOrder";

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

    private function getCurlHandle()
    {
        if ($this->curl_handle != null) {
            return $this->curl_handle;
        } else {
            return new CurlRequest();
        }

    }

    function __construct($curl_handle = null, $cacheFile = null)
    {
        $this->curl_handle = $curl_handle;

        if ($cacheFile == null) {
            $this->cacheFile = sprintf("%s/api-cred-cache.json", getcwd());

        } else {
            $this->cacheFile = $cacheFile;
        }

        $this->cache = new FileCache($this->cacheFile);

        //old stuff
        $this->delay = 5;
        $this->attempts = 6;
        $this->max_requests = 10;
        return $this;
    }

    /**
     * @throws TelflowClientAuthException
     * @throws TelflowClientException
     */
    public function checkToken()
    {
        $cacheState = $this->cache->checkCache();
        if ($cacheState['valid']) {
            // Token is still valid for Telflow access
            $this->token = $cacheState['payload'];
        } elseif ($cacheState['payload']->refresh_token != "") {
            // Invalid or expired token
            $payload = $cacheState['payload'];
            // Check if we have a refresh token
            if ($payload->refresh_token != "") {
                $this->token = $cacheState['payload'];
                // Request a new token by refresh flow
                $response = false;
                try {
                    $response = $this->getAccessToken("refresh");
                    if ($response->status() == 200) {
                        // New token returned
                        $enriched_token = $this->enrichTokenData($response->body());
                        $this->cache->writeCache($enriched_token);
                        $this->token = $enriched_token;
                    }
                } catch (TelflowClientAuthException $e) {
                    if ($e->status() == 400 && $e->message() == "Token is not active") {
                        // Refresh token might be expired.
                        // Try Full auth process.
                        $response = $this->getAccessToken("auth");

                        if ($response->status() == 200) {
                            $enriched_token = $this->enrichTokenData($response->body());
                            $this->cache->writeCache($enriched_token);
                            $this->token = $enriched_token;
                        } else {
                            echo($response->status() . "\n");
                            print_r($response->body());
                            echo("\nSomething happened :(\n");
                        }
                    } elseif ($e->status() == 401 && $e->message() == "Token is not active")
                    {
                        throw new TelflowClientAuthException($e->message(), $e->status());
                    }
                }
            }
        } else {
            try{
            // This is a request which needs full auth flow
            // maybe first ever request
            $response = $this->getAccessToken("auth");
            if ($response->status() == 200) {
                $enriched_token = $this->enrichTokenData($response->body());
                $this->cache->writeCache($enriched_token);
                $this->token = $enriched_token;
            }
            } catch (TelflowClientException $e)
            {
                throw new TelflowClientAuthException($e->message(), $e->status());
            }
        }
    }

    private function buildUrl($endpoint, $needsApiVersion = false)
    {
        if ($needsApiVersion) {
            return $this->root_url . '/' . self::TELFLOW_API_VERSION . '/' . $endpoint;
        } else {
            return $this->root_url . '/' . $endpoint;
        }

    }

    /**
     * @throws Exception
     */
    private function enrichTokenData($response)
    {
        $r = $response;
        $dt = new DateTimeImmutable("now", new DateTimeZone("Pacific/Auckland"));
        $expires_at = $dt->modify("+" . $r->expires_in . "seconds")->format("Y-m-d H:i:s");
        $refresh_expires_at = $dt->modify("+" . $r->refresh_expires_in . "seconds")->format("Y-m-d H:i:s");
        $r->issued_on = $dt->format("Y-m-d H:i:s");
        $r->refresh_by = $refresh_expires_at;
        $r->expires_at = $expires_at;
        return $r;
    }

    /**
     * Create header takes client and secret to make ready for api calls
     * @return string[]
     */
    private function createAuthHeader()
    {
        $authorization = base64_encode($this->client_id . ':' . $this->client_secret);
        return array("Authorization: Basic " . $authorization, "Content-Type: application/x-www-form-urlencoded");
    }

    /**
     * @param $type
     * @return TelflowHttpResponse
     * @throws TelflowClientAuthException
     */
    private function getAccessToken($type)
    {
        switch ($type) {
            case "auth":
                // This is an initial request
                // Running the full auth flow
                $header = $this->createAuthHeader();
                $c = $this->getCurlHandle();
                $c->setUrl($this->buildUrl(self::TELFLOW_ACCESS_TOKEN_URL));
                $c->setOption(CURLOPT_HTTPHEADER, $header)->setOption(CURLOPT_RETURNTRANSFER, true)->setOption(CURLOPT_POST, true)->setOption(CURLOPT_POSTFIELDS, "grant_type=password&username=$this->username&password=" . "$this->password");

                $response = $c->execute();
                $response_code = $c->getInfo(CURLINFO_RESPONSE_CODE);
                $content_type = $c->getInfo(CURLINFO_CONTENT_TYPE);
                $c->close();
                try {
                    return new TelflowHttpResponse($response_code, $content_type, $response);
                } catch (TelflowClientException $e) {
                    throw new TelflowClientAuthException(json_decode($response)->error_description, $response_code);
                }

            case "refresh":
                // This is a refresh flow
                $header = $this->createAuthHeader();
                $c = $this->getCurlHandle();
                $c->setUrl($this->buildUrl(self::TELFLOW_ACCESS_TOKEN_URL));
                $c->setOption(CURLOPT_HTTPHEADER, $header)->setOption(CURLOPT_RETURNTRANSFER, true)->setOption(CURLOPT_POST, true)->setOption(CURLOPT_POSTFIELDS, "grant_type=refresh_token&refresh_token=" . "{$this->token->refresh_token}");

                $response = $c->execute();
                $response_code = $c->getInfo(CURLINFO_RESPONSE_CODE);
                $content_type = $c->getInfo(CURLINFO_CONTENT_TYPE);
                $c->close();
                try {
                    return new TelflowHttpResponse($response_code, $content_type, $response);
                } catch (TelflowClientException $e) {
                    throw new TelflowClientAuthException(json_decode($response)->error_description, $response_code);
                }

        }
    }

    /**
     * @param $order_no
     * @return Exception|TelflowClientException|TelflowHttpResponse
     * @throws TelflowClientException
     */
    public function getPIID($order_no)
    {
        $parameters = ['id' => $order_no];
        $headers = array("Accept-Language: en-US",
                         "Cache-Control: max-age=0",
                         "Connection: keep-alive",
                         "Content-Type: application/json",
                         "Authorization: Bearer " . $this->token->access_token);

        $i = 1;

        // Re-check and refresh token if neccesary :)
        $this->checkToken();
        $c = $this->getCurlHandle();
        $c->setUrl($this->buildUrl(self::TELFLOW_ORDER_ENDPOINT, true) . "?" . http_build_query($parameters));
        $c->setOption(CURLOPT_HTTPHEADER, $headers)->setOption(CURLOPT_CUSTOMREQUEST, 'GET')->setOption(CURLOPT_RETURNTRANSFER, true);

        $response = $c->execute();
        $response_code = $c->getInfo(CURLINFO_RESPONSE_CODE);
        $content_type = $c->getInfo(CURLINFO_CONTENT_TYPE);
        $c->close();

        try {
            $response = new TelflowHttpResponse($response_code, $content_type, $response);

        } catch (TelflowClientException $e) {
            $response = $e;
        }

        if ($response->status() == 200){
            if (isset($response->body()->customerOrders[0]->orderItem[0]->product[0]->id)){
                return new TelflowHttpResponse($response->status(),
                    // Just text as want to return string
                    "application/text",
                    $response->body()->customerOrders[0]->orderItem[0]->product[0]->id);
            } else {
                return new TelflowHttpResponse(404,
                                                'application/json',
                                                       '{"error": "Not Found","error_description": "Unable to find PIID for input order"}');
            }

        } else {
            return $response;
        }
    }
}