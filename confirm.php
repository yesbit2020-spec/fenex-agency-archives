<?php
// --- 設定：ここにいとーちゃんのメールアドレスを入れてね ---
$to = "ここに自分のメールアドレスを記入"; 
$subject = "【Fenex Agency】AI診断エントリー届いたよっ！";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $age   = $_POST['age'];
    $pref  = $_POST['pref'];
    
    // 写真（ファイル）の処理
    $file_tmp  = $_FILES['photo']['tmp_name'];
    $file_name = $_FILES['photo']['name'];

    // メールの境界線（バウンダリ）を作成
    $boundary = md5(uniqid(rand()));
    
    // ヘッダー作成
    $headers = "From: " . $email . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";

    // メッセージ本文
    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= "エントリーがありました！\n\nお名前：$name\n年齢：$age\n地域：$pref\nメール：$email\n\n" . "\r\n";

    // 写真の添付処理
    if (is_uploaded_file($file_tmp)) {
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"$file_name\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"$file_name\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($file_tmp))) . "\r\n";
    }

    $body .= "--" . $boundary . "--";

    // 送信！
    if (mail($to, $subject, $body, $headers)) {
        echo "<h1>送信完了！</h1><p>いとーちゃん、写真もしっかり送ったわよ。返信を楽しみに待っててね💋</p>";
    } else {
        echo "送信エラー。ヘテムルの設定を確認してみて。";
    }
}
?>