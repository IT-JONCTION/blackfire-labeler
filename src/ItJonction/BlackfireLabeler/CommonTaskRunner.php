<?php
declare(strict_types=1);

namespace ItJonction\BlackfireLabeler;

use Exception;

require_once __DIR__ . '/../../../redisValues.php';
require_once __DIR__ . '/BlackfireLabeler.php';
require_once __DIR__ . '/RedisConfig.php';

/**
 * Run a task with a Blackfire labeler.
 *
 * @param  callable  $blackfireTask
 * @return void
 */
function runBlackfireTask(callable $blackfireTask): void {
    global $scopedRedisEnvValues;

    if ($scopedRedisEnvValues) {
        try {
            $redisConfig = new RedisConfig('blackfireLabeler', $scopedRedisEnvValues);
            $blackfireLabeler = new BlackfireLabeler($redisConfig->getClient());

            $blackfireTask($blackfireLabeler);

        } catch (Exception $e) {
            error_log("Error in Blackfire task: " . $e->getMessage());
        }
    }
}
