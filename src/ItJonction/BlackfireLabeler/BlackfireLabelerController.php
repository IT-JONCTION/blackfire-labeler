<?php
declare(strict_types=1);

namespace ItJonction\BlackfireLabeler;

require_once __DIR__ . '/CommonTaskRunner.php';

runBlackfireTask(function(BlackfireLabeler $blackfireLabeler) {
    $blackfireLabeler->labelEntryPointsForBlackfire();
});

// Rest of the application runs after...
