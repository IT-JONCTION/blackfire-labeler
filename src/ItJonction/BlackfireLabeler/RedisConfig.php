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
     * @param array|null $redisValues Optional Redis configuration values.
     */
    public function __construct(string $persistentID = 'bob', ?array $redisValues = null)
    {
        list(
            $redisHost,
            $redisPort,
            $redisAuth,
            $redisDatabase,
            $redisContext
        ) = $this->getRedisConf($redisValues ?? []);

        $this->redis = new Redis();
        $this->configure($redisHost, $redisPort, $redisAuth, $redisDatabase, $persistentID, $redisContext);
    }

    /**
     * Retrieve Redis configuration from provided parameters or environment variables.
     *
     * @param array $params Optional parameters for configuration.
     * @return array Configuration settings for Redis.
     */
    private function getRedisConf(array $params = []): array
    {
        $keys = [
            'REDIS_HOST' => '',
            'REDIS_PORT' => 0,
            'REDIS_USERNAME' => '',
            'REDIS_PASSWORD' => '',
            'REDIS_DB' => 0,
            'REDIS_SECURE' => false,
        ];

        foreach ($keys as $key => &$value) {
            if (isset($params[$key])) {
                $value = $params[$key];
            } elseif (isset($_ENV[$key])) {
                $value = $_ENV[$key];
            }

            if ($key === 'REDIS_PORT' || $key === 'REDIS_DB') {
                $value = (int) $value;
            }
        }

        $context = null;
        if ((bool)$keys['REDIS_SECURE'] === true) {
            $context = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ];
        }

        return [
            $keys['REDIS_HOST'],
            $keys['REDIS_PORT'],
            [$keys['REDIS_USERNAME'], $keys['REDIS_PASSWORD']],
            $keys['REDIS_DB'],
            $context
        ];
    }

    /**
     * Configures the Redis client instance.
     *
     * @param string $redisHost The hostname of the Redis server.
     * @param int $redisPort The port number of the Redis server.
     * @param mixed $redisAuth String password or array(username, password).
     * @param int $redisDatabase The database index of the Redis server.
     * @param string $persistentID The persistent ID used for the connection.
     * @param array|null $context Optional context parameters.
     */
    private function configure(
        string $redisHost,
        int $redisPort,
        mixed $redisAuth,
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
