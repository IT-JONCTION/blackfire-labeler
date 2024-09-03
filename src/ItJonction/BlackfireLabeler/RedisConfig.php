<?php
declare(strict_types=1);

namespace ItJonction\BlackfireLabeler;

use Redis;
use RedisException;

class RedisConfig
{
    const TIMEOUT = 5;
    const RETRY_INTERVAL = 100;
    const READ_TIMEOUT = 5;

    private Redis $redis;

    /**
     * RedisConfig constructor.
     *
     * @param string $persistentID The persistent ID used for the connection.
     * @param array $redisValues An array containing Redis configuration values.
     */
    public function __construct(string $persistentID = 'bob', ?array $redisValues)
    {
        if ($redisValues === null) {
            throw new \InvalidArgumentException("Redis configuration values must be provided.");
        }

        $redisHost = $redisValues['REDIS_HOST'];
        $redisPort = (int)$redisValues['REDIS_PORT'];
        $redisUsername = $redisValues['REDIS_USERNAME'];
        $redisPassword = $redisValues['REDIS_PASSWORD'];
        $redisDatabase = (int)$redisValues['REDIS_DB'];
        $isSecure = (bool)$redisValues['REDIS_SECURE'];

        $context = [];
        if ($isSecure) {
            $context = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ];
        }

        $this->redis = new Redis();
        $this->configure($redisHost, $redisPort, [$redisUsername, $redisPassword], $redisDatabase, $persistentID, $context);
    }

    /**
     * Configures the Redis client instance.
     *
     * @param string $redisHost The hostname of the Redis server.
     * @param int $redisPort The port number of the Redis server.
     * @param mixed $redisAuth Array containing username and password.
     * @param int $redisDatabase The database index of the Redis server.
     * @param string $persistentID The persistent ID used for the connection.
     * @param array|null $context The optional context parameters.
     */
    private function configure(
        string $redisHost,
        int $redisPort,
        array $redisAuth,
        int $redisDatabase,
        string $persistentID,
        ?array $context
    ): void {
        try {
            $this->redis->pconnect(
                $redisHost,
                $redisPort,
                self::TIMEOUT,
                $persistentID,
                self::RETRY_INTERVAL,
                self::READ_TIMEOUT,
                $context
            );
            $this->redis->auth($redisAuth);
            $this->redis->select($redisDatabase);
        } catch (RedisException $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * Provides access to the Redis client instance.
     *
     * @return Redis The Redis client instance.
     */
    public function getClient(): Redis
    {
        return $this->redis;
    }
}
