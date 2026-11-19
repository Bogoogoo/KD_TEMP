<?php
// 臨時測試檔：略過 @ 轉換，直接把群組訊息送到測試 webhook（不 @ 任何人）。測完請刪除。
// 注意：會真的發一則訊息到測試群組。
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

function mask($text, $url)
{
    if ($url !== '') {
        $text = str_replace($url, '(webhook)', $text);
    }
    return preg_replace('/key=[^\s"&\\\\]+/', 'key=***', $text);
}

$url = '';
try {
    $svc = $app->make(App\Services\DailyReport\DailyReportFirstLevelWarningService::class);
    $methods = (new ReflectionClass($svc))->getMethods();

    $find = function ($needles) use ($methods) {
        foreach ($needles as $n) {
            foreach ($methods as $m) {
                if (stripos($m->getName(), $n) !== false) {
                    return $m;
                }
            }
        }
        return null;
    };

    $scan = $find(array('scan'));
    $build = $find(array('buildWebhook', 'buildNotification'));
    $send = $find(array('sendToWebhook'));

    if (!$scan || !$build || !$send) {
        echo "找不到需要的方法，現有方法如下：\n";
        foreach ($methods as $m) {
            echo " - " . $m->getName() . "\n";
        }
        exit;
    }
    echo "掃描: " . $scan->getName() . " / 組訊息: " . $build->getName() . " / 發送: " . $send->getName() . "\n";

    $url = (string) env('WECOM_WEBHOOK_FIRST_LEVEL_WARNING');
    if ($url === '') {
        echo "webhook 未設定\n";
        exit;
    }

    $list = callp($svc, $scan->getName());
    echo "警示筆數: " . count($list) . "\n";

    $buildArgs = array_slice(array($list, array()), 0, $build->getNumberOfParameters());
    $content = callp($svc, $build->getName(), $buildArgs);
    echo "--- 訊息內容 ---\n" . $content . "\n----------------\n";

    $sendArgs = array_slice(array($url, $content, array()), 0, $send->getNumberOfParameters());
    $res = callp($svc, $send->getName(), $sendArgs);
    echo "發送結果: " . mask(json_encode($res, JSON_UNESCAPED_UNICODE), $url) . "\n";
} catch (Exception $e) {
    echo "錯誤: " . get_class($e) . " " . mask($e->getMessage(), $url) . "\n";
}
