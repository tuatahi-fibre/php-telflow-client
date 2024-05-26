<?php


use Tuatahifibre\TelflowClient\TelflowClient;
use Tuatahifibre\TelflowClient\HttpRequestInterface;
use Tuatahifibre\TelflowClient\FileCache;
use PHPUnit\Framework\TestCase;

class TelflowClientTest extends TestCase
{
    private $cacheFile;
    private $cache;
    public function setUp()
    {
        $this->cacheFile = sprintf("%s/api-cred-cache.json", getcwd());
        $this->client = new Tuatahifibre\TelflowClient\TelflowClient(null, $this->cacheFile);
        $this->cache = new FileCache($this->cacheFile);
        parent::setUp();
    }

    public function testNewInstance()
    {
        $this->assertInstanceOf(TelflowClient::class,
            $this->client, "Failed to get an instance of class");
    }

    public function testSetUsername()
    {
        $this->client->setUsername("someuser");
        $this->assertEquals("someuser", $this->client->username);
    }

    public function testSetPassword()
    {
        $this->client->setPassword("somepassword");
        $this->assertEquals("somepassword", $this->client->password,
            "Assertion failed");
    }

    public function testSupplyMockInterface()
    {
        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $this->client = new Tuatahifibre\TelflowClient\TelflowClient($mock);

    }

    public function testAuthenticateNoCache()
    {
        // Ensure the cachefile is no longer present.
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $mock->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(file_get_contents(__DIR__ .
                            '/Responses/AuthenticationResponseSuccess.json'))
            );
        $mock->expects($this->any())
            ->method('setOption')
            ->will($this->returnValue($mock));
        $mock->expects($this->exactly(2))
            ->method('getInfo')
            ->willReturn($this->returnValue(200),
                $this->returnValue('application/json'));

        $this->client = new Tuatahifibre\TelflowClient\TelflowClient($mock);
        $this->client->setUsername("some.api.user")
            ->setPassword("some.user.password")
            ->setClientId("a.client.id")
            ->setClientSecret("som.client.secret")
            ->setBaseUrl("https://some.website.address.co.nz")
            ->checkToken();
    }

    public function testAuthenticateCached()
    {
        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $this->client = new Tuatahifibre\TelflowClient\TelflowClient($mock);
        $this->client->setUsername("some.api.user")
            ->setPassword("some.user.password")
            ->setClientId("a.client.id")
            ->setClientSecret("som.client.secret")
            ->setBaseUrl("https://some.website.address.co.nz")
            ->checkToken();
    }

    public function testAuthenticationRefreshFlow()
    {
        // Update the cache expiry to ensure refresh is triggered.
        $cached_details = $this->cache->checkCache();
        $data = $cached_details['payload'];
        // Set expiry to issued time
        $data->expires_at = $data->issued_on;
        // Write back
        $this->cache->writeCache($data);

        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $this->client = new Tuatahifibre\TelflowClient\TelflowClient($mock, $this->cacheFile);
        $mock->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(file_get_contents(__DIR__ .
                        "/Responses/AuthenticationResponseRefreshTokenUsed.json"))
            );
        $mock->expects($this->any())
            ->method('setOption')
            ->will($this->returnValue($mock));
        $mock->expects($this->exactly(2))
            ->method('getInfo')
            ->willReturn($this->returnValue(200),
                $this->returnValue('application/json'));

        $this->client->setUsername("some.api.user")
            ->setPassword("some.user.password")
            ->setClientId("a.client.id")
            ->setClientSecret("som.client.secret")
            ->setBaseUrl("https://some.website.address.co.nz")
            ->checkToken();
    }

    public function testAuthenticationRefreshFlowExpiredToken()
    {
        // Update the cache to ensure refresh is triggered.
        $cached_details = $this->cache->checkCache();
        $data = $cached_details['payload'];
        // Set expiry to issued time
        $data->expires_at = $data->issued_on;
        // Write back
        $this->cache->writeCache($data);

        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $this->client = new Tuatahifibre\TelflowClient\TelflowClient($mock, $this->cacheFile);
        $mock->expects($this->exactly(2))
            ->method('execute')
            ->willReturn($this->returnValue(file_get_contents(__DIR__ .
                        '/Responses/AuthenticationResponseExpiredToken.json')),
            $this->returnValue(file_get_contents(__DIR__ . '/Responses/AuthenticationResponseRefreshTokenUsed.json'))
            );
        $mock->expects($this->exactly(4))
            ->method('getInfo')
            ->willReturn($this->returnValue(400),
                $this->returnValue('application/json'),
                $this->returnValue(200),
                $this->returnValue('application/json'));
        $mock->expects($this->any())
            ->method('setOption')
            ->will($this->returnValue($mock));

        // Setup Client
        $this->client->setUsername("some.api.user")
            ->setPassword("some.user.password")
            ->setClientId("a.client.id")
            ->setClientSecret("som.client.secret")
            ->setBaseUrl("https://some.website.address.co.nz")
            ->checkToken();

        // Delete cache file
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public static function tearDownAfterClass(){
        $cacheFile = sprintf("%s/api-cred-cache.json", getcwd());
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}