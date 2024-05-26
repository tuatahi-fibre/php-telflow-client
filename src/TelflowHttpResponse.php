<?php

namespace Tuatahifibre\TelflowClient;

class TelflowHttpResponse implements HttpResponseInterface
{
    private $response_code;
    private $content_type;
    private $body;

    /**
     * @throws TelflowClientException
     */
    public function __construct($response_code,
                                $content_type,
                                $body)
    {
        $this->body = $body;
        $this->content_type = $content_type;
        $this->response_code = $response_code;
        $this->parseInput();
        return $this;
    }

    /**
     * @throws TelflowClientException
     */
    private function parseInput()
    {
//        echo("\n");
//        echo("Content Type: " . $this->content_type . "\n");
//        echo("\n");
//        echo("Body: " . $this->body . "\n");
//        echo("\n");
//        echo("Code: " . $this->response_code . "\n");
        if ($this->response_code != 200) {
            $data = json_decode($this->body);
            throw new TelflowClientException("Error \"$data->error\" : $data->error_description",
                $this->response_code);

        }
    }
    /**
     * @inheritDoc
     */
    public function body()
    {
        if (preg_match("|^application/json.*|", $this->content_type)){
            // Decode JSON object
            return json_decode($this->body);
        } else {
            // Just send the string
            return (string)$this->body;
        }
    }

    /**
     * @inheritDoc
     */
    public function status()
    {
        return $this->response_code;
    }
}