<?php


use Tuatahifibre\TelflowClient\TelflowClientException;
use Tuatahifibre\TelflowClient\TelflowHttpResponse;
use PHPUnit\Framework\TestCase;

class TelflowHttpResponseTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->client = new TelflowHttpResponse(200,
            "application/text",
            "boo");
    }


    public function testBody()
    {
        $this->assertEquals("boo", $this->client->body());
    }

    public function testStatus()
    {
        $this->assertEquals(200, $this->client->status());
    }

    public function testJSONResponse()
    {
        $this->client = new TelflowHttpResponse(200,
            "application/json",
            file_get_contents(__DIR__ . '/Responses/AuthenticationResponseSuccess.json')
        );

        $this->assertEquals(200, $this->client->status());
        $this->assertInstanceOf('StdClass', $this->client->body());
        $json = $this->client->body();
        $this->assertEquals(3600, $json->expires_in);
        $this->assertEquals("someaccesstoken-Xh4m7oA", $json->access_token);

    }

    public function testJSONErrorResponse()
    {
        $this->expectException(TelflowClientException::class);
        $this->client = new TelflowHttpResponse(400,
            "application/json",
            '{
                        "error": "invalid_grant",
                        "error_description": "Token is not active"
                   }'
        );

    }
}
