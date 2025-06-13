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

    protected function setUp()
    {
        // Use sys_get_temp_dir() for portable temp directory handling
        $this->cacheFile = sprintf("%s/api-cred-cache.json", sys_get_temp_dir());
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        $this->cache = new FileCache($this->cacheFile);
    }
    public function testGetPIID()
    {

        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        
        // Track execution order
        $executionCount = 0;
        
        // Mock execute to return auth response first, then orders response
        $mock->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function() use (&$executionCount) {
                $executionCount++;
                if ($executionCount === 1) {
                    return file_get_contents(__DIR__ . '/Responses/AuthenticationResponseSuccess.json');
                }
                return file_get_contents(__DIR__ . '/Responses/CustomerOrdersResponseValid.json');
            });

        // Mock getInfo to return proper response codes and content types
        $mock->expects($this->any())
            ->method('getInfo')
            ->willReturnCallback(function($type) use (&$executionCount) {
                return $type === CURLINFO_RESPONSE_CODE ? 200 : 'application/json';
            });

        $mock->expects($this->any())
            ->method('setOption')
            ->willReturn($mock);

        $mock->expects($this->any())
            ->method('close')
            ->willReturn(true);
                
        $this->client = new TelflowClient($mock, $this->cacheFile);
        $this->client->setUsername("some-api-user")
            ->setPassword("a.password")
            ->setClientId("client-id-goes-here")
            ->setClientSecret("a-secret-shhhh")
            ->setBaseUrl("https://some-base-url")
            ->checkToken();

        $piid = $this->client->getPIID("XXX00000XXXXXXX");

        $this->assertEquals('XXX00000XXXXXXX', $piid->body());

    }

     public function testGetPIIDTimeout()
     {
        
         $mock = $this->getMockBuilder('Tuatahifibre\TelflowClient\CurlRequest')
             ->setMethods(['execute', 'getInfo', 'setOption', 'close'])
             ->getMock();

         // Track which request we're handling
         $requestType = 'auth';
         $attempts = 0;

         // Mock setOption
         $mock->expects($this->any())
             ->method('setOption')
             ->willReturnCallback(function($option, $value) use ($mock, &$requestType) {
                 if ($option === CURLOPT_URL) {
                     $requestType = (strpos($value, 'token') !== false) ? 'auth' : 'piid';
                 }
                 return $mock;
             });

         // Mock getInfo
         $mock->expects($this->any())
             ->method('getInfo')
             ->willReturnCallback(function($type) use (&$requestType) {
                 return $type === CURLINFO_RESPONSE_CODE ?
                        ($requestType === 'auth' ? 200 : CURLE_OPERATION_TIMEOUTED) :
                        'application/json';
             });

         // Mock execute
         $mock->expects($this->any())
             ->method('execute')
             ->willReturnCallback(function() use (&$requestType, &$attempts) {
                 if ($requestType === 'auth') {
                     return file_get_contents(__DIR__ . '/Responses/AuthenticationResponseSuccess.json');
                 }
                
                 $attempts++;
                 throw new TelflowClientException(
                     'Operation timed out after 30000 milliseconds',
                     CURLE_OPERATION_TIMEOUTED
                 );
             });

         // Mock close
         $mock->expects($this->any())
             ->method('close')
             ->willReturn(true);

         $client = new TelflowClient($mock, $this->cacheFile);
         $client->setUsername("test.user")
                ->setPassword("test.pass")
                ->setClientId("test.client")
                ->setClientSecret("test.secret")
                ->setBaseUrl("https://test.api.com");

         $this->expectException(TelflowClientException::class);
         $this->expectExceptionCode(CURLE_OPERATION_TIMEOUTED);
         $this->expectExceptionMessage('Operation timed out after 30000 milliseconds');

         $client->getPIID('TEST-ORDER-123');
     }
}
