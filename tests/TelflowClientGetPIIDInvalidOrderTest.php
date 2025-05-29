<?php


use Tuatahifibre\TelflowClient\TelflowClient;
use PHPUnit\Framework\TestCase;

class TelflowClientGetPIIDInvalidOrderTest extends TestCase
{
    private $cacheFile;
    private $cache;
    /**
     * @var TelflowClient
     */
    private $client;

    public function testGetPIID()
    {
        $this->cacheFile = sprintf("%s/api-cred-cache.json", getcwd());
        // Ensure the cachefile is no longer present.
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $mock->expects($this->exactly(2))
            ->method('execute')
            ->willReturn($this->returnValue(file_get_contents(__DIR__ . '/Responses/AuthenticationResponseSuccess.json')),
                $this->returnValue(file_get_contents(__DIR__ . '/Responses/CustomerOrdersResponseNotFound.json'))
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
        $this->client->setUsername("ApiSvc")
            ->setPassword("ConnectAPI1!")
            ->setClientId("uff-integration")
            ->setClientSecret("1b715878-836b-45cc-99ca-bdb9e8a8012a")
            ->setBaseUrl("https://portal-e2e.ultrafastfibre.co.nz")
            ->checkToken();

        $this->expectException(Tuatahifibre\TelflowClient\TelflowClientException::class);
        $piid = $this->client->getPIID("ORD000018196855");

    }
}
