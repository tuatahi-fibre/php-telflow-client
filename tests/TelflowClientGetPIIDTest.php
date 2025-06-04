<?php


use Tuatahifibre\TelflowClient\TelflowClient;
use PHPUnit\Framework\TestCase;

class TelflowClientGetPIIDTest extends TestCase
{
    private $cacheFile;
    private $cache;
    /**
     * @var TelflowClient
     */
    private $client;

    public function testGetPIID()
    {
        $this->cacheFile = '/tmp/cache/api-creds.json';
        // Ensure the cachefile is no longer present.
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $mock->expects($this->exactly(2))
            ->method('execute')
            ->willReturn($this->returnValue(file_get_contents(realpath(__DIR__ . '/Responses/AuthenticationResponseSuccess.json'))),
                $this->returnValue(file_get_contents(__DIR__ . '/Responses/CustomerOrdersResponseValid.json'))
            );
        $mock->expects($this->any())
            ->method('setOption')
            ->will($this->returnValue($mock));
        $mock->expects($this->any())
            ->method('close')
            ->will($this->returnValue($mock));
        $mock->expects($this->exactly(4))
            ->method('getInfo')
            ->willReturn($this->returnValue(200),
                $this->returnValue('application/json'),
                $this->returnValue(200),
                $this->returnValue('application/json'));
        $this->client = new Tuatahifibre\TelflowClient\TelflowClient($mock, $this->cacheFile);
        $this->client->setUsername("some-api-user")
            ->setPassword("a.password")
            ->setClientId("client-id-goes-here")
            ->setClientSecret("a-secret-shhhh")
            ->setBaseUrl("https://some-base-url")
            ->checkToken();

        $piid = $this->client->getPIID("ORD000018196855");
        $this->assertEquals('XXX00000XXXXXXX', $piid->body());

    }
}
