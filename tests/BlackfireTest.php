<?php
declare(strict_types=1);

namespace ItJonction\BlackfireLabeler\Tests\BlackfireIntegration;

use Exception;
use PHPUnit\Framework\TestCase;
use ItJonction\BlackfireLabeler\BlackfireLabeler;
use ItJonction\BlackfireLabeler\RedisConfig;
use Redis;
use RedisException;

class BlackfireTest extends TestCase
{
  const REDIS_INCLUDE_FILES_KEY = 'included_files';
  const REDIS_EXPIRATION_TIME = 100800; //28 hours
  private Redis $redis;
  private BlackfireLabeler $labeler;
  private string $testArchiveFilePath;
  private string $testNotExistentFilePath;
  private string $testLockedFilePath;
  private string $restrictedFilePath;
  private string $testRepeatedLineFilePath;
  private string $testFilePath;
  private string $testSpecialFilePath;
  private array $lines;
  private array $specialLines;
  private array $repeatedLines;
  private string $testLineToDelete;
  private string $repeatedLineToDelete;
  /**
   * @var false|resource
   */
  private $fileHandle;
  private string|false $emptyFilePath;

  /**
   * @return void
   */
  public function tearDownLockedFile(): void
  {
// tear down locked file
    flock($this->fileHandle, LOCK_UN);
    fclose($this->fileHandle);
    unlink($this->testLockedFilePath);
  }

  /**
   * @return void
   * @throws RedisException
   */
  public function tearDownRedisConnection(): void
  {
// tear down Redis connection and clean up any remaining data
    $this->redis->del(BlackfireLabeler::REDIS_HASH_KEY);
    $this->redis->close();
  }

  /**
   * @return void
   */
  public function tearDownRestrictedFile(): void
  {
// tearDown restricted File
    chmod($this->restrictedFilePath, 0666);
    // Clean up: remove the temporary file
    unlink($this->restrictedFilePath);
  }

  /**
   * @return void
   */
  public function setupFileLockHere(): void
  {
//setup file lock here
    $this->testLockedFilePath = tempnam(sys_get_temp_dir(), 'test');

    // Write some content to the file
    file_put_contents($this->testLockedFilePath, $this->lines);

    // Open the file and acquire an exclusive lock
    $this->fileHandle = fopen($this->testLockedFilePath, 'r');
    flock($this->fileHandle, LOCK_EX);
    //end file lock here
  }

  /**
   * @return void
   */
  public function setUpATemporaryFileForTesting(): void
  {
// set up a temporary file for testing
    $this->emptyFilePath = tempnam(sys_get_temp_dir(), 'test');

    // Make sure the file is empty
    file_put_contents($this->emptyFilePath, '');
  }

  /**
   * @return void
   */
  public function setUpStandardsForTest(): void
  {
    $this->lines = [
      "Line 1\n",
      "Line 2\n",
      "Line to be deleted\n",
      "Line 3\n",
      "Line 4\n"
    ];
    $this->testFilePath = tempnam(sys_get_temp_dir(), 'test');
    // Define a line to be deleted
    $this->testLineToDelete = 'Line to be deleted';
    // Write the lines to the temporary file
    file_put_contents($this->testFilePath, implode('', $this->lines));
  }

  /**
   * @return void
   */
  public function setUpNonExistentFileTest(): void
  {
// Set up non-existent file test
    $this->testNotExistentFilePath = '/path/to/nonexistent/file.txt';
  }

  /**
   * @return void
   */
  public function setUpTheTestArchiveFilePath(): void
  {
// set up the test archive file path
    $this->testArchiveFilePath = __DIR__.'/testArchive.log';

    // Ensure the test file is not already present
    if (file_exists($this->testArchiveFilePath)) {
      unlink($this->testArchiveFilePath);
    }
  }

  /**
   * @return void
   */
  public function connectToTheRedisInstance(): void
  {
    $redisValues = [
      'REDIS_HOST' => '127.0.0.1',    // Assuming the Redis service is running on localhost
      'REDIS_PORT' => 6379,           // Default Redis port
      'REDIS_USERNAME' => 'default',         // Assuming no username is needed
      'REDIS_PASSWORD' => '',         // Assuming no password is needed
      'REDIS_DB' => 0,                // Using the default database
      'REDIS_SECURE' => false         // No SSL/TLS needed for local testing
    ];
      
    // Connect to the Redis instance
    $redisConfig = new RedisConfig('blackfireLabeler', $redisValues);

    // Create a new instance of BlackfireLabeler
    $this->redis = $redisConfig->getClient();
  }

  /**
   * @return void
   */
  public function setupRestrictedPermissionFileTest(): void
  {
    $this->restrictedFilePath = tempnam(sys_get_temp_dir(), 'restricted');
    file_put_contents($this->restrictedFilePath, $this->lines);
    chmod($this->restrictedFilePath, 0444); // Read-only permissions
  }

  /**
   * @return void
   */
  public function setUpSpecialCharsFileTest(): void
  {
    $this->testSpecialFilePath = tempnam(sys_get_temp_dir(), 'special');
    $this->specialLines = [
      "Line 1\n",
      "Another line\n",
      "Line with special characters: !@#$%^&*()\n",
      "Line with utf-8 characters: äöüß\n",
      "Line to be deleted\n",
      "Final line\n"
    ];
    file_put_contents($this->testSpecialFilePath, $this->specialLines);
  }

  /**
   * @return void
   */
  public function setUpRepeatedFileTest(): void
  {
    $this->testRepeatedLineFilePath = tempnam(sys_get_temp_dir(), 'repeated');
    $this->repeatedLines = [
      "Line 1\n",
      "Line 2\n",
      "Line to be deleted\n",
      "Line to be deleted\n",
      "Line 3\n",
      "Line 4\n"
    ];
    $this->repeatedLineToDelete = "Line to be deleted";
    file_put_contents($this->testRepeatedLineFilePath, $this->repeatedLines);
  }

  protected function setUp(): void
  {
    $this->setUpRepeatedFileTest();
    $this->setUpSpecialCharsFileTest();
    $this->setUpStandardsForTest();
    $this->setupRestrictedPermissionFileTest();
    $this->connectToTheRedisInstance();
    $this->setUpTheTestArchiveFilePath();
    $this->setUpNonExistentFileTest();
    $this->setUpATemporaryFileForTesting();
    $this->setupFileLockHere();

    // Instantiate BlackfireLabeler with the real Redis client
    $this->labeler = new SpyBlackfireLabeler($this->redis);

    parent::setUp();
  }


  // Test generateRequestHash
  public function testGenerateRequestHash(): void {
    // Define known inputs
    $entryPoint = '/test/endpoint.php';
    $getData = ['param1' => 'value1', 'param2' => 'value2'];
    $requestPath = '/test/endpoint.php?param1=value1&param2=value2';

    // Call generateRequestHash with known inputs
    $generatedHash = $this->labeler->generateRequestHash($entryPoint, $getData, $requestPath);

    // Calculate expected MD5 hash
    $expectedHash = md5($entryPoint . serialize($getData) . $requestPath);

    // Assert that the output is the expected MD5 hash
    $this->assertEquals($expectedHash, $generatedHash);
  }

  public function testIsRequestLogged(): void {
    // Define a known hash and a random unknown hash
    $knownHash = 'known_hash';
    $unknownHash = 'unknown_hash';
    $dummyData = ['dummy' => 'data'];

    try {// Prepopulate Redis with the known hash
      $this->redis->hSet(BlackfireLabeler::REDIS_HASH_KEY, $knownHash, json_encode($dummyData));
    } catch (RedisException) {
      $this->fail("RedisException thrown when prepopulating Redis with the known hash.");
    }

    try {// Call isRequestLogged with the known hash and assert true
      $this->assertTrue($this->labeler->isRequestLogged($knownHash));// Call isRequestLogged with an unknown hash and assert false
      $this->assertFalse($this->labeler->isRequestLogged($unknownHash));// Clean up Redis - remove the known hash from Redis
      $this->redis->hDel(BlackfireLabeler::REDIS_HASH_KEY, $knownHash);
    } catch (RedisException) {
      $this->fail("RedisException thrown when checking if the known hash is logged.");
    }
  }

  public function testLabelEntryPointsForBlackfireCallsSetBlackfireTransactionName(): void {
    // Set up environment for the test
    $_SERVER['SCRIPT_NAME'] = '/test.php';

    // Call labelEntryPointsForBlackfire
    $this->labeler->labelEntryPointsForBlackfire();

    // Assert that setBlackfireTransactionName was called
    $this->assertTrue($this->labeler->isSetBlackfireTransactionNameCalled());
  }

  public function testLabelEntryPointsForBlackfire(): void {
    // Simulate a web request environment
    $_SERVER['SCRIPT_NAME'] = '/test.php';
    $_GET = ['param' => 'value'];

    // Call labelEntryPointsForBlackfire
    $this->labeler->labelEntryPointsForBlackfire();

    // Generate the hash that should be created by the method
    $hash = md5($_SERVER['SCRIPT_NAME'] . serialize($_GET));

    try {// Assert that the Redis hash has been correctly updated
      $loggedData = $this->redis->hGet(BlackfireLabeler::REDIS_HASH_KEY, $hash);
      $this->assertNotEmpty($loggedData);// Clean up Redis
      $this->redis->del(BlackfireLabeler::REDIS_HASH_KEY);
    } catch (RedisException) {
      $this->fail("RedisException thrown when checking if the hash has been correctly updated.");
    }
  }

  public function testArchiveLoggedRequests(): void {
    // Prepopulate Redis with test data
    $testHash = 'test_hash';
    $testData = ['entryPoint' => '/test.php', 'GET' => ['param' => 'value']];

    try {
      $this->redis->hSet(BlackfireLabeler::REDIS_HASH_KEY, $testHash,
        json_encode($testData));// Call archiveLoggedRequests
      $this->labeler->archiveLoggedRequests($this->testArchiveFilePath);
    } catch (RedisException) {
      $this->fail("RedisException thrown when prepopulating Redis with test data.");
    }

    // Assert that the archive file is created
    $this->assertFileExists($this->testArchiveFilePath);

    // Assert that the file contains the expected data
    $contents = file_get_contents($this->testArchiveFilePath);
    $this->assertStringContainsString($testHash, $contents);
    $this->assertStringContainsString(json_encode($testData), $contents);

    try {// Optionally, assert that the Redis hash is cleared
      $this->assertFalse((bool) $this->redis->exists(BlackfireLabeler::REDIS_HASH_KEY));
    } catch (RedisException) {
      $this->fail("RedisException thrown when checking if the Redis hash is cleared.");
    }

    // Clean up: Delete the test archive file
    if (file_exists($this->testArchiveFilePath)) {
      unlink($this->testArchiveFilePath);
    }
  }

  public function testLogRequestToRedis()
  {
    $hash = 'test_hash';
    $entryPoint = '/index.php';
    $getData = ['key' => 'value'];
    $postData = ['key' => 'value'];
    $requestPath = '/index.php?key=value';
    $filePath = '/var/www/html/index.php';

    try {// Log request to Redis
      $this->labeler->logRequestToRedis($hash, $entryPoint, $getData, $requestPath, $filePath, $postData);
      $loggedData = $this->redis->hGet(BlackfireLabeler::REDIS_HASH_KEY, $hash);
      $this->assertNotEmpty($loggedData);
      $this->assertJson($loggedData);// Clean up
      $this->redis->del(BlackfireLabeler::REDIS_HASH_KEY);
    } catch (RedisException) {
      $this->fail("RedisException thrown when logging request to Redis.");
    }
  }

  public function testDeleteMultipleInstancesOfLine()
  {
    // Ensure the file contains the initial lines
    $this->assertStringEqualsFile($this->testRepeatedLineFilePath, implode('', $this->repeatedLines));

    // Call the method under test
    $this->labeler->deleteLineFromFile($this->testRepeatedLineFilePath, $this->repeatedLineToDelete);

    // Expected file content after deletion
    $expectedContent = [
      "Line 1\n",
      "Line 2\n",
      "Line 3\n",
      "Line 4"
    ];

    // Check the file content is as expected after the deletion
    $this->assertStringEqualsFile($this->testRepeatedLineFilePath, implode('', $expectedContent));
  }

  public function testDeleteExistingLine()
  {
    // Ensure the file contains the initial lines
    $this->assertStringEqualsFile($this->testFilePath, implode('', $this->lines));

    // Call the method under test
    $this->labeler->deleteLineFromFile($this->testFilePath, $this->testLineToDelete);

    // Expected file content after deletion
    $expectedContent = [
      "Line 1\n",
      "Line 2\n",
      "Line 3\n",
      "Line 4"
    ];

    // Check the file content is as expected after the deletion
    $this->assertStringEqualsFile($this->testFilePath, implode('', $expectedContent));
  }
  public function testDeleteLineFromFileWithNonExistentFile()
  {
    // There should be no file at the test file path
    $this->assertFileDoesNotExist($this->testNotExistentFilePath);

    // Call the method under test
    try {
      $this->labeler->deleteLineFromFile($this->testNotExistentFilePath, $this->testLineToDelete);

      // If no exception is thrown, the test passes
      $this->assertTrue(true);
    } catch (Exception) {
      // If an exception is thrown, the test fails
      $this->fail("An exception should not have been thrown for a non-existent file.");
    }
  }

  public function testDeleteLineFromFileWithPermissionIssues()
  {
    // Call the method under test
    try {
      $this->labeler->deleteLineFromFile($this->testFilePath, $this->testLineToDelete);

      // If no exception is thrown, the test passes
      $this->assertTrue(true);
    } catch (Exception) {
      // If an exception is thrown, the test fails
      $this->fail("An exception should not have been thrown, even with permission issues.");
    }
  }

  public function testDeleteLineFromEmptyFile()
  {
    // Ensure the file is empty before the test
    $this->assertFileExists($this->emptyFilePath);
    $this->assertStringEqualsFile($this->emptyFilePath, '');

    // Call the method under test
    try {
      $this->labeler->deleteLineFromFile($this->emptyFilePath, $this->testLineToDelete);

      // Check the file content remains empty after function call
      $this->assertStringEqualsFile($this->emptyFilePath, '');

      // If no exception is thrown, the test passes
      $this->assertTrue(true);
    } catch (Exception) {
      // If an exception is thrown, the test fails
      $this->fail("An exception should not have been thrown for an empty file.");
    }
  }

  public function testDeleteLineFromFileWithFileLocking()
  {
    // Call the method under test
    try {

      $this->labeler->deleteLineFromFile($this->testLockedFilePath, $this->testLineToDelete);
      // If no exception is thrown, the test passes
      $this->assertTrue(true);
    } catch (Exception) {
      // If an exception is thrown, the test fails
      $this->fail("An exception should not have been thrown due to file locking.");
    }
  }

  public function testDeleteLineWithSpecialCharacters()
  {
    // Ensure the file contains the initial lines
    $this->assertStringEqualsFile($this->testSpecialFilePath, implode('', $this->specialLines));

    // Call the method under test
    $this->labeler->deleteLineFromFile($this->testSpecialFilePath, "Line with special characters: !@#$%^&*()");
    $this->labeler->deleteLineFromFile($this->testSpecialFilePath, "Line with utf-8 characters: äöüß");

    // Expected file content after deletion
    $expectedContent = [
      "Line 1\n",
      "Another line\n",
      "Line to be deleted\n",
      "Final line"
    ];

    // Check the file content is as expected after the deletion
    $this->assertStringEqualsFile($this->testSpecialFilePath, implode('', $expectedContent));
  }

  public function testLogIncludedFilesToRedis(): void
  {
    // Call the method under test
    $this->labeler->logIncludedFilesToRedis();

    // Check if the hash key exists in Redis
    $this->assertTrue((bool)$this->redis->exists(self::REDIS_INCLUDE_FILES_KEY));

    // Retrieve the hash value
    $hashValues = $this->redis->hGetAll(self::REDIS_INCLUDE_FILES_KEY);
    $this->assertIsArray($hashValues);
    $this->assertNotEmpty($hashValues);

    // Test the hash content
    foreach ($hashValues as $jsonFiles) {
      // Check if the stored value is valid JSON
      $decodedFiles = json_decode($jsonFiles, true);
      $this->assertNotNull($decodedFiles, 'Stored string is not valid JSON');

      // Check that the JSON is an array of file paths
      $this->assertIsArray($decodedFiles, 'Decoded JSON is not an array');

      // Optionally, assert that every element in the array is a string
      foreach ($decodedFiles as $filePath) {
        $this->assertIsString($filePath, 'Expected file path to be a string');

        echo $filePath . "\n";

        // Optionally, if you know the files that should definitely be there
        $this->assertStringContainsString('php', $filePath);
      }
    }

    // Check the expiration time
    $ttl = $this->redis->ttl(self::REDIS_INCLUDE_FILES_KEY);
    $this->assertGreaterThan(0, $ttl);
    $this->assertLessThanOrEqual(self::REDIS_EXPIRATION_TIME, $ttl);
  }

  public function testNormalSizedData()
  {
        $postData = [
          'normalField' => 'normalData'
        ];

        $processedData = $this->labeler->processPostData($postData);

        $this->assertEquals($postData, $processedData, 'Normal sized data should remain unchanged.');
  }

  public function testLargeData()
  {
        $largeString = str_repeat('a', 1025); // 1025 characters
        $postData = [
          'largeField' => $largeString
        ];

        $processedData = $this->labeler->processPostData($postData);

        $this->assertEquals('[Data too large to log]', $processedData['largeField'], 'Large data should be replaced with a placeholder.');
  }

  public function testComplexData()
  {
        $postData = [
          'fileField' => ['name' => 'test.jpg', 'size' => 5000]
        ];

        $processedData = $this->labeler->processPostData($postData);

        $this->assertEquals('[Complex Data]', $processedData['fileField'], 'Complex data (like files) should be replaced with a placeholder.');
  }

  public function testMixedData()
  {
        $postData = [
          'normalField' => 'normalData',
          'largeField' => str_repeat('a', 1025),
          'fileField' => ['name' => 'test.jpg', 'size' => 5000]
        ];

        $expectedResult = [
          'normalField' => 'normalData',
          'largeField' => '[Data too large to log]',
          'fileField' => '[Complex Data]'
        ];

        $processedData = $this->labeler->processPostData($postData);

        $this->assertEquals($expectedResult, $processedData, 'Mixed data should be processed correctly.');
  }

  protected function tearDown(): void
  {

    parent::tearDown();
    try {
      $this->tearDownRedisConnection();
    } catch (RedisException) {
      $this->fail("RedisException thrown when tearing down Redis connection.");
    }

    // Clean up: remove the temporary files
    unlink($this->testFilePath);
    unlink($this->emptyFilePath);
    $this->tearDownRestrictedFile();
    $this->tearDownLockedFile();

  }
}




class SpyBlackfireLabeler extends BlackfireLabeler {
  private bool $setBlackfireTransactionNameCalled = false;

  public function setBlackfireTransactionName(string $hash): void {
    parent::setBlackfireTransactionName($hash);
    $this->setBlackfireTransactionNameCalled = true;
  }

  public function isSetBlackfireTransactionNameCalled(): bool {
    return $this->setBlackfireTransactionNameCalled;
  }
}
