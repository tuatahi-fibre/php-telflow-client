<?php

namespace Tuatahifibre\TelflowClient;

interface HttpRequestInterface
{
    /**
     * @param $name
     * @param $value
     * @return self
     */
    public function setOption($name, $value);
    /**
     * @return self
     */

    public function setUrl($url);

    /**
     * @return self
     */

    /**
     * @return mixed
     */
    public function execute();

    /**
     * @param $name
     * @return mixed
     */
    public function getInfo($name);

    /**
     * @return mixed
     */
    public function close();
}