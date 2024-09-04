<?php
declare(strict_types=1);

namespace ItJonction\BlackfireLabeler;

require_once __DIR__.'/CommonTaskRunner.php';

runBlackfireTask(function ($blackfireLabeler) {
  $blackfireLabeler->archiveLoggedRequests(sys_get_temp_dir().'/blackfire_' . date('Y-m-d') . '.log');
});
