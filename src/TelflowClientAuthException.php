<?php

namespace Tuatahifibre\TelflowClient;

class TelflowClientAuthException extends \Exception
{
    protected $code;
    protected $message;

    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, $previous = null) {
        // some code
        $this->code = $code;
        $this->message = $message;
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function status()
    {
        return $this->code;
    }

    public function message()
    {
        return $this->message;
    }

}