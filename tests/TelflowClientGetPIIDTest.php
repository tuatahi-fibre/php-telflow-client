<?php


use Tuatahifibre\TelflowClient\FileCache;
use Tuatahifibre\TelflowClient\TelflowClient;
use PHPUnit\Framework\TestCase;
use Tuatahifibre\TelflowClient\TelflowClientException;

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
        $this->cacheFile = sprintf("%s/api-cred-cache.json", sys_get_temp_dir());
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

    // public function testGetPIIDTimeout()
    // {
    //     // Use sys_get_temp_dir() for portable temp directory handling
    //     $this->cacheFile = sprintf("%s/api-cred-cache.json", sys_get_temp_dir());
    //     if (file_exists($this->cacheFile)) {
    //         unlink($this->cacheFile);
    //     }
        
    //     $mock = $this->getMockBuilder('Tuatahifibre\TelflowClient\CurlRequest')
    //         ->setMethods(['execute', 'getInfo', 'setOption', 'close'])
    //         ->getMock();

    //     // Track which request we're handling
    //     $requestType = 'auth';
    //     $attempts = 0;

    //     // Mock setOption
    //     $mock->expects($this->any())
    //         ->method('setOption')
    //         ->willReturnCallback(function($option, $value) use ($mock, &$requestType) {
    //             if ($option === CURLOPT_URL) {
    //                 $requestType = (strpos($value, 'token') !== false) ? 'auth' : 'piid';
    //             }
    //             return $mock;
    //         });

    //     // Mock getInfo
    //     $mock->expects($this->any())
    //         ->method('getInfo')
    //         ->willReturnCallback(function($type) use (&$requestType) {
    //             return $type === CURLINFO_RESPONSE_CODE ? 
    //                    ($requestType === 'auth' ? 200 : CURLE_OPERATION_TIMEOUTED) : 
    //                    'application/json';
    //         });

    //     // Mock execute
    //     $mock->expects($this->any())
    //         ->method('execute')
    //         ->willReturnCallback(function() use (&$requestType, &$attempts) {
    //             if ($requestType === 'auth') {
    //                 return file_get_contents(__DIR__ . '/Responses/AuthenticationResponseSuccess.json');
    //             }
                
    //             $attempts++;
    //             throw new TelflowClientException(
    //                 'Operation timed out after 30000 milliseconds',
    //                 CURLE_OPERATION_TIMEOUTED
    //             );
    //         });

    //     // Mock close
    //     $mock->expects($this->any())
    //         ->method('close')
    //         ->willReturn(true);

    //     $client = new TelflowClient($mock, $this->cacheFile);
    //     $client->setUsername("test.user")
    //            ->setPassword("test.pass")
    //            ->setClientId("test.client")
    //            ->setClientSecret("test.secret")
    //            ->setBaseUrl("https://test.api.com");

    //     $this->expectException(TelflowClientException::class);
    //     $this->expectExceptionCode(CURLE_OPERATION_TIMEOUTED);
    //     $this->expectExceptionMessage('Operation timed out after 30000 milliseconds');

    //     $client->getPIID('TEST-ORDER-123');
    // }
}
