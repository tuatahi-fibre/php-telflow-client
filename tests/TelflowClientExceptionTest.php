<?php


use Tuatahifibre\TelflowClient\TelflowClientException;
use PHPUnit\Framework\TestCase;

class TelflowClientExceptionTest extends TestCase
{

    public function test__toString()
    {
        $c = new TelflowClientException("Error", 400);
        $this->assertEquals("Tuatahifibre\TelflowClient\TelflowClientException: [400]: Error\n" , $c->__toString());

    }
}
