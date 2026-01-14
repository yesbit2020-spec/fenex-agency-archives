<?php
// show_logs.php — 簡易的にログの有無・パーミッション・末尾を表示します
$dir = __DIR__;
$files = scandir($dir);
$logfiles = ['mail_debug.log', 'mail_full_debug.log'];

header('Content-Type: text/plain; charset=UTF-8');
echo "Directory: {$dir}\n\n";
echo "Files in directory:\n";
foreach ($files as $f) {
    echo "- " . $f . "\n";
}
echo "\n";

foreach ($logfiles as $lf) {
    $path = $dir . DIRECTORY_SEPARATOR . $lf;
    echo "--- {$lf} ---\n";
    echo 'exists: ' . (file_exists($path) ? 'yes' : 'no') . "\n";
    if (file_exists($path)) {
        echo 'size: ' . filesize($path) . " bytes\n";
        echo 'writable: ' . (is_writable($path) ? 'yes' : 'no') . "\n";
        echo 'perms: ' . substr(sprintf('%o', fileperms($path)), -4) . "\n";
        echo "\nLast 50 lines:\n";
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            echo "(failed to read file)\n";
        } else {
            $last = array_slice($lines, -50);
            foreach ($last as $line) echo $line . "\n";
        }
    }
    echo "\n";
}

echo "(end)\n";
?>