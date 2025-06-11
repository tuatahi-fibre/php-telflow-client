<?php
use Tuatahifibre\TelflowClient\TelflowClient;
use Tuatahifibre\TelflowClient\FileCache;
use PHPUnit\Framework\TestCase;
use Tuatahifibre\TelflowClient\TelflowClientAuthException;
use Tuatahifibre\TelflowClient\TelflowClientException;

class TelflowClientTest extends TestCase
{
    private $cacheFile;
    private $cache;
    private $client;

    public function setUp()
    {
        $this->cacheFile = sprintf("%s/api-cred-cache.json", sys_get_temp_dir());
        parent::setUp();
    }

    public function tearDown()
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testAuthenticateNoCache()
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $this->cache = new FileCache($this->cacheFile);

        // Debug: Check if response file exists
        $responsePath = __DIR__ . '/Responses/AuthenticationResponseSuccess.json';

        if (is_dir(__DIR__ . '/Responses')) {
            foreach (scandir(__DIR__ . '/Responses') as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "  - " . $file . "\n";
                }
            }
        } else {
            error_log("Responses directory does not exist!\n");
        }

        // Check if we can read the file
        if (file_exists($responsePath)) {
            $content = file_get_contents(realpath($responsePath));
        } else {
            // Fallback: create a mock response inline
            $content = json_encode([
                'access_token' => 'mock_token_123',
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]);
        }

        $mock = $this->createMock('Tuatahifibre\TelflowClient\HttpRequestInterface');
        $mock->expects($this->once())
            ->method('execute')
            ->willReturn($content);

        $mock->expects($this->any())
            ->method('setOption')
            ->willReturn($mock);

        $mock->expects($this->exactly(2))
            ->method('getInfo')
            ->willReturnOnConsecutiveCalls(200, 'application/json');

        $this->client = new TelflowClient($mock, $this->cacheFile);

        $this->client->setUsername("some.api.user")
            ->setPassword("some.user.password")
            ->setClientId("a.client.id")
            ->setClientSecret("som.client.secret")
            ->setBaseUrl("https://some.website.address.co.nz")
            ->checkToken();


        if (file_exists($this->cacheFile)) {
            error_log("Cache file size: " . filesize($this->cacheFile) . " bytes\n");
        }

        $this->assertFileExists($this->cacheFile, "Cache file was not created.");
    }
}