<?php

namespace Tuatahifibre\TelflowClient;

/**
 * CurlRequest class with retry functionality
 */
class CurlRequest implements HttpRequestInterface
{
    /**
     * @var resource CURL handle
     */
    private $handle = null;

    /**
     * @var integer Maximum number of retries
     */
    private $maxRetries = 3;

    /**
     * @var integer Delay between retries in microseconds
     */
    private $retryDelay = 1000000; // 1 second

    /**
     * Set max number of retries
     * @param integer $retries
     * @return CurlRequest
     */
    public function setMaxRetries($retries)
    {
        $this->maxRetries = (int)$retries;
        return $this;
    }

    /**
     * Set delay between retries in milliseconds
     * @param integer $milliseconds
     * @return CurlRequest
     */
    public function setRetryDelay($milliseconds)
    {
        $this->retryDelay = (int)$milliseconds * 1000; // Convert to microseconds
        return $this;
    }

    /**
     * @param $url
     */
    public function __construct()
    {
        $this->handle = curl_init();
        if ($this->handle === false) {
            throw new TelflowClientException('Failed to initialize CURL handle');
        }
    }

    public function setUrl($url)
    {
        $this->setOption(CURLOPT_URL, $url);
        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @return $this Returns the object to allow multiple setOption
     */
    public function setOption($name, $value)
    {
        if (!is_resource($this->handle)) {
            $this->handle = curl_init();
        }
        
        if (curl_setopt($this->handle, $name, $value) === false) {
            throw new TelflowClientException(
                sprintf('Failed to set CURL option %s', $name)
            );
        }
        return $this;
    }

    /**
     * @return bool|string
     * @throws TelflowClientException
     */
    public function execute()
    {
        $attempts = 0;
        $lastError = null;
        $lastErrno = null;

        while ($attempts <= $this->maxRetries) {
            $response = curl_exec($this->handle);
            
            if ($response !== false) {
                return $response;
            }

            $lastError = curl_error($this->handle);
            $lastErrno = curl_errno($this->handle);
            
            // Only retry on timeout or connection errors
            if (!in_array($lastErrno, [
                CURLE_OPERATION_TIMEOUTED,
                CURLE_COULDNT_CONNECT,
                CURLE_COULDNT_RESOLVE_HOST
            ])) {
                break;
            }

            $attempts++;
            
            if ($attempts <= $this->maxRetries) {
                error_log(sprintf(
                    "Request attempt %d failed: %s. Retrying in %d ms...",
                    $attempts,
                    $lastError,
                    $this->retryDelay / 1000
                ));
                usleep($this->retryDelay);
            }
        }
        
        // If we got here, all retries failed or error wasn't retryable
        if ($lastErrno == CURLE_OPERATION_TIMEOUTED) {
            throw new TelflowClientException(
                sprintf("Request timed out after %d attempts: %s", $attempts, $lastError),
                408
            );
        }
        
        throw new TelflowClientException(
            sprintf("CURL Error after %d attempts: %s", $attempts, $lastError),
            $lastErrno
        );
    }

    /**
     * Sets default timeout values
     */
    public function setDefaultTimeouts()
    {
        $this->setOption(CURLOPT_CONNECTTIMEOUT, 10)  // Connection timeout
             ->setOption(CURLOPT_TIMEOUT, 30);        // Request timeout
        return $this;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getInfo($name)
    {
        return curl_getinfo($this->handle, $name);
    }

    /**
     * @return void
     */
    public function close()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }


}
