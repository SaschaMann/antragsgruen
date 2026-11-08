#!/usr/bin/env php
<?php

declare(strict_types=1);

// Run one workload per process to make peak memory measurements comparable.
require dirname(__DIR__) . '/tests/Support/ConflictCheck/bootstrap.php';
Yii::$app->errorHandler->unregister(); // Keep CLI failures visible and return a failing exit status.

use app\components\diff\AmendmentConflictChecker;
use Tests\Support\ConflictCheck\Fixture;

$count = (int)($argv[1] ?? 50);
$paragraphs = (int)($argv[2] ?? 20);
$dense = ($argv[3] ?? 'sparse') === 'dense';
$runs = (int)($argv[4] ?? 3);
if ($count < 2 || $paragraphs < 1 || $runs < 1) {
    fwrite(STDERR, "Usage: php bin/benchmark-amendment-conflicts.php COUNT PARAGRAPHS sparse|dense RUNS\n");
    exit(1);
}
$timings = [];
for ($run = 0; $run < $runs; $run++) {
    $start = hrtime(true);
    try {
    $sections = Fixture::sections($count, $paragraphs, dense: $dense);
    $prepared = hrtime(true);
    $result = AmendmentConflictChecker::compare($sections);
    $compared = hrtime(true);
    if ($result['warnings']) {
        throw new RuntimeException('Benchmark comparisons failed');
    }
    $titles = $urls = [];
    foreach (array_keys($sections) as $id) {
        $titles[$id] = 'A-' . $id;
        $urls[$id] = '/motion/1/amendment/' . $id;
    }
    $html = Yii::$app->view->renderFile(Yii::getAlias('@app/views/motion/_amendment_conflict_results.php'), compact('result', 'titles', 'urls'));
    $end = hrtime(true);
    $timings[] = ['fixture_ms' => ($prepared - $start) / 1e6, 'compare_ms' => ($compared - $prepared) / 1e6, 'render_ms' => ($end - $compared) / 1e6, 'total_ms' => ($end - $start) / 1e6];
    $bytes = strlen($html);
    $conflicts = count($result['pairs']);
    unset($result, $sections, $html);
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
echo json_encode([
    'php' => PHP_VERSION, 'amendments' => $count, 'paragraphs' => $paragraphs, 'dense' => $dense,
    'pairs' => $count * ($count - 1) / 2, 'directional_checks' => $count * ($count - 1),
    'conflicts' => $conflicts, 'response_bytes' => $bytes, 'peak_mib' => memory_get_peak_usage(true) / 1048576,
    'memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time'),
    'runs' => $timings,
], JSON_UNESCAPED_SLASHES) . "\n";
