<?php

namespace Tuatahifibre\TelflowClient;

/**
 *
 */
class CurlRequest implements HttpRequestInterface
{
    /**
     * @var false|resource
     */
    private $handle = null;

    /**
     * @param $url
     */
    public function __construct()
    {
        $this->handle = curl_init();
        return $this;
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
        curl_setopt($this->handle, $name, $value);
        return $this;
    }

    /**
     * @return bool|string
     */
    public function execute()
    {
        return curl_exec($this->handle);
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
        curl_close($this->handle);
    }


}
