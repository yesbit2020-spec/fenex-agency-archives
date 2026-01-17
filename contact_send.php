<?php
/**
 * Fenex Agency - お問い合わせ送信プログラム
 * 2026.01.17 版
 */

// --- 設定 ---
$to_email   = "kentaro-itou@live.jp"; // いとーちゃんの受信したいアドレスに変更してね
$from_email = "system@fenex.jp"; 
$subject    = "【Fenex Agency】新規お問い合わせが届きました";

// --- データ取得 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = htmlspecialchars($_POST['c_name'] ?? '不明', ENT_QUOTES, 'UTF-8');
    $email   = htmlspecialchars($_POST['c_email'] ?? '不明', ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($_POST['c_msg'] ?? '内容なし', ENT_QUOTES, 'UTF-8');

    // --- メール本文 ---
    $body = "Fenex Agency 公式サイトよりお問い合わせがありました。\n\n";
    $body .= "--------------------------------------------------\n";
    $body .= "【お名前】\n{$name}\n\n";
    $body .= "【メールアドレス】\n{$email}\n\n";
    $body .= "【お問い合わせ内容】\n{$message}\n";
    $body .= "--------------------------------------------------\n";
    $body .= "送信元IP: " . $_SERVER['REMOTE_ADDR'] . "\n";

    // --- メールヘッダー ---
    $headers = "From: {$from_email}\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // --- 送信実行 ---
    if (mb_send_mail($to_email, $subject, $body, $headers)) {
        http_response_code(200);
        echo "Success";
    } else {
        http_response_code(500);
        echo "Error";
    }
} else {
    http_response_code(403);
    echo "Forbidden";
}