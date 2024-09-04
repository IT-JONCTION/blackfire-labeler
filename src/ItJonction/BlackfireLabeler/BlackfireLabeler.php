<?php
declare(strict_types=1);

namespace ItJonction\BlackfireLabeler;

use Redis;
use RedisException;

class BlackfireLabeler
{
    /**
     * The expiration time for the Redis hash key in seconds.
     * 28 hours = 100800 seconds
     */
    const REDIS_EXPIRATION_TIME = 100800; // 28 hours
    const REDIS_HASH_KEY = 'request_logs';
    const REDIS_INCLUDE_FILES_KEY = 'included_files';

    /**
     * The Redis client instance used for connecting to the Redis data store.
     *
     * @var Redis
     */
    private Redis $redis;

    /**
     * BlackfireLabeler constructor.
     *
     * @param Redis $redis The Redis client instance.
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Generates a unique hash for the current request based on the entry point script and GET parameters.
     *
     * @param string $entryPoint The path of the entry point script.
     * @param array $getData An associative array of GET parameters from the request.
     * @param string $requestPath The path of the current request.
     *
     * @return string A 32-character MD5 hash uniquely representing the request.
     */
    public function generateRequestHash(string $entryPoint, array $getData, string $requestPath): string
    {
        $serializedGetData = serialize($getData);
        $concatenatedData = $entryPoint . $serializedGetData . $requestPath;
        return md5($concatenatedData);
    }

    /**
     * Checks if the given request hash has already been logged in Redis.
     *
     * @param string $hash The unique hash of the request to check.
     *
     * @return bool True if the request is already logged, False otherwise.
     * @throws RedisException
     */
    public function isRequestLogged(string $hash): bool
    {
        return $this->redis->hExists(self::REDIS_HASH_KEY, $hash);
    }

    /**
     * Logs the included files to Redis using a hash map
     * and sets an expiration time for the hash key.
     *
     * @return void
     * @throws RedisException
     */
    public function logIncludedFilesToRedis(): void
    {
        // Get included files
        $includedFiles = get_included_files();

        // JSON encode the array
        $jsonEncodedIncludedFiles = json_encode($includedFiles);

        // Use a transaction to ensure atomicity
        $this->redis->multi();

        // Use content to generate a unique hash
        $hash = md5($jsonEncodedIncludedFiles);

        // Store the JSON encoded data in a Redis hash
        $this->redis->hSet(self::REDIS_INCLUDE_FILES_KEY, $hash, $jsonEncodedIncludedFiles);

        // Set an expiration time for the hash key using the class constant
        $this->redis->expire(self::REDIS_INCLUDE_FILES_KEY, self::REDIS_EXPIRATION_TIME);

        // Execute the transaction
        $this->redis->exec();
    }

    /**
     * Logs the request details to Redis using a hash map and sets an expiration time for the hash key.
     *
     * @param string $hash The unique hash representing the request.
     * @param string $entryPoint The script path that serves as the entry point for the current request.
     * @param array $getData The associative array of GET parameters for the current request.
     * @param string $requestPath The path of the current request.
     * @param string $filePath The path of the entry point script.
     * @param array $postData The POST data associated with the request.
     *
     * @return void
     * @throws RedisException If there is an issue writing to Redis.
     */
    public function logRequestToRedis(
        string $hash,
        string $entryPoint,
        array $getData,
        string $requestPath,
        string $filePath,
        array $postData
    ): void {
        // Create an array with entry point and GET data
        $requestDetails = [
            'entryPoint' => $entryPoint,
            'GET' => $getData,
            'POST' => $this->processPostData($postData),
            'requestPath' => $requestPath,
            'filePath' => $filePath
        ];

        // JSON encode the array
        $jsonEncodedRequestDetails = json_encode($requestDetails);

        // Use a transaction to ensure atomicity
        $this->redis->multi();

        // Store the JSON encoded data in a Redis hash
        $this->redis->hSet(self::REDIS_HASH_KEY, $hash, $jsonEncodedRequestDetails);

        // Set an expiration time for the hash key using the class constant
        $this->redis->expire(self::REDIS_HASH_KEY, self::REDIS_EXPIRATION_TIME);

        // Execute the transaction
        $this->redis->exec();
    }

    /**
     * Sets the transaction name in the Blackfire profiler.
     *
     * @param string $hash The unique hash of the request to be used as the transaction name.
     *
     * @return void
     */
    public function setBlackfireTransactionName(string $hash): void
    {
        // Check if Blackfire is loaded
        if (!extension_loaded('blackfire')) {
            // Early return if Blackfire is not available
            return;
        }

        // Set the transaction name for Blackfire profiling
        \BlackfireProbe::setTransactionName($hash);
    }

    /**
     * Orchestrates the logging of request details and setting of transaction names for profiling.
     *
     * @return void
     */
    public function labelEntryPointsForBlackfire(): void
    {
        // Get the entry point and generate a hash for the request
        $entryPoint = $_SERVER['SCRIPT_NAME'];
        $requestPath = $_SERVER['REQUEST_URI'] ?? '';
        $filePath = $_SERVER['SCRIPT_FILENAME'];
        $getData = $_GET;
        $postData = $_POST;
        $hash = $this->generateRequestHash($entryPoint, $getData, $requestPath);

        // Check if this request hash is already logged in Redis
        try {
            if (!$this->isRequestLogged($hash)) {
                // If not, log the request details
                $this->logRequestToRedis($hash, $entryPoint, $getData, $requestPath, $filePath, $postData);
            }
        } catch (RedisException $e) {
            // Log the exception
            error_log($e->getMessage());
        }

        // Set the transaction name for Blackfire profiling
        $this->setBlackfireTransactionName($hash);
    }

    /**
     * Deletes a line from a file.
     *
     * @param string $filePath The path of the file to be modified.
     * @param string $lineToDelete The line to be deleted from the file.
     *
     * @return void
     */
    public function deleteLineFromFile(string $filePath, string $lineToDelete): void
    {
        // Check if the file exists
        if (!file_exists($filePath)) {
            // Early return if the file does not exist
            return;
        }

        // Read the file contents into an array
        $fileContents = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Filter out the lines that exactly match the line to delete
        $fileContents = array_filter($fileContents, function ($line) use ($lineToDelete) {
            return trim($line) !== $lineToDelete;
        });

        // Re-index the array and prepare the content for writing
        $fileContents = array_values($fileContents);
        $updatedContent = implode("\n", $fileContents);

        // Write the updated file contents back to the file
        file_put_contents($filePath, $updatedContent);
    }

    /**
     * Archives all logged requests from Redis hash to a log file.
     *
     * @param string $logFilePath The file path where the archived logs will be stored.
     *
     * @return void
     * @throws RedisException
     */
    public function archiveLoggedRequests(string $logFilePath): void
    {
        // Fetch all entries from the Redis 'request_logs' hash
        $allEntries = $this->redis->hGetAll(self::REDIS_HASH_KEY);

        // Prepare the content to be logged
        $logContent = '';
        foreach ($allEntries as $hash => $jsonEncodedRequestDetails) {
            $logContent .= "Hash: $hash, Details: $jsonEncodedRequestDetails\n";
        }

        // Check if there is anything to log
        if ($logContent) {
            // Append the log content to the log file
            file_put_contents($logFilePath, $logContent, FILE_APPEND | LOCK_EX);
        }

        // Clear the hash after archiving to free up memory
        $this->redis->del(self::REDIS_HASH_KEY);
    }

    /**
     * Removes the included files from the log file.
     *
     * @param string $logFilePath The file path where the archived logs will be stored.
     *
     * @return void
     * @throws RedisException
     */
    public function removeIncludedFilesFromLog(string $logFilePath): void
    {
        // Fetch all entries from the Redis 'included_files' hash
        $allEntries = $this->redis->hGetAll(self::REDIS_INCLUDE_FILES_KEY);

        // Check if there are any entries
        if (!$allEntries) {
            return;
        }

        // Get the JSON encoded included files
        $jsonEncodedIncludedFiles = array_values($allEntries)[0];

        // Decode the JSON encoded included files
        $includedFiles = json_decode($jsonEncodedIncludedFiles);

        // Loop through the included files
        foreach ($includedFiles as $includedFile) {
            // Delete the included file from the log file
            $this->deleteLineFromFile($logFilePath, $includedFile);
        }
    }

    /**
     * Process POST data to remove large values and complex data structures.
     *
     * @param array $postData
     * @param int $maxSize
     * @return array
     */
    public function processPostData(array $postData, int $maxSize = 1024): array
    {
        $processedData = [];
        foreach ($postData as $key => $value) {
            // Check if the value is an array or an object,
            if (is_array($value) || is_object($value)) {
                $processedData[$key] = '[Complex Data]';
            } elseif (strlen($value) > $maxSize) {
                // If the value is a string and exceeds the max size, replace with a placeholder
                $processedData[$key] = '[Data too large to log]';
            } else {
                // Otherwise, include the value as-is
                $processedData[$key] = $value;
            }
        }
        return $processedData;
    }
}
