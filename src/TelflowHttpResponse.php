<?php

namespace Tuatahifibre\TelflowClient;

class TelflowHttpResponse 
{
    private $response_code;
    private $content_type;
    private $body;
    private $raw_response;

    /**
     * @param integer $response_code HTTP response code
     * @param string $content_type Content-Type header
     * @param string $input Raw response body
     */
    public function __construct($response_code, $content_type, $input) 
    {
        $this->response_code = $response_code;
        $this->content_type = $content_type;
        $this->raw_response = $input;
        $this->parseInput($input);
    }

    /**
     * Parse raw response input
     * @param string $input Raw response body
     * @throws TelflowClientException
     */
    private function parseInput($input) 
    {
        // Handle empty responses
        if (empty($input)) {
            return;
        }

        // Store raw response
        $this->raw_response = $input;

        // Try to decode JSON responses
        if (strpos($this->content_type, 'application/json') !== false) {
            $decoded = json_decode($input);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new TelflowClientException('Failed to parse JSON response', $this->response_code);
            }
            
            // Check for error response in JSON
            if ($this->response_code >= 400) {
                $message = isset($decoded->error_description) ? $decoded->error_description : 
                         (isset($decoded->message) ? $decoded->message : 'Unknown error');
                throw new TelflowClientException($message, $this->response_code);
            }
            
            $this->body = $decoded;
            return;
        }

        // Store raw response for non-JSON content
        $this->body = $input;
    }

    /**
     * Get response code
     * @return integer
     */
    public function responseCode()
    {
        return $this->response_code;
    }

    /**
     * Get content type
     * @return string
     */
    public function contentType()
    {
        return $this->content_type;
    }

    /**
     * Get response body
     * @return mixed Decoded JSON object or raw string
     */
    public function body()
    {
        return $this->body;
    }

    /**
     * Set response body
     * @param mixed $body New body content
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * Get response status code
     * @return integer HTTP status code
     */
    public function status()
    {
        return $this->response_code;
    }

    /**
     * Get raw response
     * @return string
     */
    public function rawResponse()
    {
        return $this->raw_response;
    }
}