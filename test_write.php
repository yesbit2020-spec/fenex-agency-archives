<?php
// test_write.php
// ブラウザで開いて、同ディレクトリにある mail_debug.log へ書き込みできるか確認します。
$log = __DIR__ . '/mail_debug.log';
$now = date('c');
$info = [];
$info[] = "datetime={$now}";
$info[] = 'script=' . __FILE__;
$info[] = 'dir_writable=' . (is_writable(__DIR__) ? 'yes' : 'no');
$info[] = 'file_exists=' . (file_exists($log) ? 'yes' : 'no');
$info[] = 'file_writable=' . (is_writable($log) ? 'yes' : 'no');
$info[] = 'uid=' . (function_exists('getmyuid') ? @getmyuid() : 'n/a');
$info[] = 'gid=' . (function_exists('getmygid') ? @getmygid() : 'n/a');

$entry = implode(' | ', $info) . " | test=write\n";

$result = @file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);

header('Content-Type: text/plain; charset=UTF-8');
echo "Log path: {$log}\n";
echo "Dir writable: " . (is_writable(__DIR__) ? 'yes' : 'no') . "\n";
echo "File exists: " . (file_exists($log) ? 'yes' : 'no') . "\n";
echo "File writable: " . (is_writable($log) ? 'yes' : 'no') . "\n";

if ($result === false) {
    $err = error_get_last();
    echo "Write result: FAILED\n";
    echo "Error message: " . ($err['message'] ?? 'no details') . "\n";
} else {
    echo "Write result: OK ({$result} bytes written)\n";
    echo "--- Last lines of file ---\n";
    $lines = @file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        echo "(failed to read file)\n";
    } else {
        $last = array_slice($lines, -10);
        foreach ($last as $line) {
            echo $line . "\n";
        }
    }
}

echo "\n注意: テスト完了後はこのファイルを削除してください。";
