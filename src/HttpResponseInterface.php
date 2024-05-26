<?php

namespace Tuatahifibre\TelflowClient;

interface HttpResponseInterface
{
    /**
     * @return mixed
     */
    public function body();

    /**
     * @return int
     */
    public function status();

}