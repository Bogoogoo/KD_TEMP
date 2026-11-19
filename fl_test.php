<?php
// 臨時測試檔：只掃描第一階警示並印出筆數與前 3 筆，不發送。測完請刪除。
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(
    new Symfony\Component\Console\Input\ArrayInput(array('command' => 'list')),
    new Symfony\Component\Console\Output\BufferedOutput()
);

function callp($obj, $method, $args = array())
{
    $r = new ReflectionMethod($obj, $method);
    $r->setAccessible(true);
    return $r->invokeArgs($obj, $args);
}

try {
    $svc = $app->make(App\Services\DailyReport\DailyReportFirstLevelWarningService::class);

    $name = null;
    foreach ((new ReflectionClass($svc))->getMethods() as $m) {
        if (stripos($m->getName(), 'scan') !== false) {
            $name = $m->getName();
            break;
        }
    }
    if ($name === null) {
        echo "找不到 scan 開頭的方法，請回報給我\n";
        exit;
    }
    echo "使用方法: {$name}\n";

    $list = callp($svc, $name);
    echo "警示筆數: " . count($list) . "\n\n";

    foreach (array_slice($list, 0, 3) as $row) {
        $w = isset($row['user']['name']) ? $row['user']['name'] : '?';
        $d = isset($row['user']['department']['ch_name']) ? $row['user']['department']['ch_name'] : '?';
        $f = isset($row['first_level_assign_user']['name']) ? $row['first_level_assign_user']['name'] : '?';
        echo "{$w}（{$d}）→ 第一階：{$f}\n";
    }
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
