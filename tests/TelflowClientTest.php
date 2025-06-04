<?php
use Tuatahifibre\TelflowClient\TelflowClient;
use Tuatahifibre\TelflowClient\FileCache;
use PHPUnit\Framework\TestCase;

class TelflowClientTest extends TestCase
{
    private $cacheFile;
    private $cache;
    private $client;

    public function setUp()
    {
        $this->cacheFile = sprintf("%s/api-cred-cache.json", sys_get_temp_dir());
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $this->cache = new FileCache($this->cacheFile);
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
        // Debug: Check if response file exists
        $responsePath = __DIR__ . '/Responses/AuthenticationResponseSuccess.json';
        error_log("Response file path: " . $responsePath . "\n");
        error_log("Response file exists: " . (file_exists($responsePath) ? 'YES' : 'NO') . "\n");
        error_log("Current __DIR__: " . __DIR__ . "\n");
        error_log("Current working dir: " . getcwd() . "\n");
        
        if (is_dir(__DIR__ . '/Responses')) {
            error_log("Responses directory contents:\n");
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
            error_log("Response file content length: " . strlen($content) . " bytes\n");
            error_log("Response file content preview: " . substr($content, 0, 100) . "...\n");
        } else {
            // Fallback: create a mock response inline
            $content = json_encode([
                'access_token' => 'mock_token_123',
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]);
            error_log("Using fallback mock response\n");
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

        // Debug cache file creation
        error_log("Cache file path: " . $this->cacheFile . "\n");
        error_log("Cache file exists: " . (file_exists($this->cacheFile) ? 'YES' : 'NO') . "\n");
        if (file_exists($this->cacheFile)) {
            error_log("Cache file size: " . filesize($this->cacheFile) . " bytes\n");
        }

        $this->assertFileExists($this->cacheFile, "Cache file was not created.");
    }
}