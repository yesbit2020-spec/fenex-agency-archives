<?php
// --- 設定：ここにいとーちゃんのメールアドレスを入れてね ---
$to = "yesbit2020+agency@gmail.com"; 
$subject = "【Fenex Agency】AI診断エントリー届いたよっ！";

// 日本語メールの設定
mb_language("Japanese");
mb_internal_encoding("UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $errors = [];

    // 入力取得と簡易サニタイズ
    $name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $age   = isset($_POST['age'])   ? trim($_POST['age'])   : '';
    $pref  = isset($_POST['pref'])  ? trim($_POST['pref'])  : '';

    // バリデーション
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'お名前は必須で、100文字以内で入力してください。';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '有効なメールアドレスを入力してください。';
    }
    if ($age !== '' && !preg_match('/^\d{1,3}$/', $age)) {
        $errors[] = '年齢は数字で入力してください。';
    }
    if ($pref === '' || mb_strlen($pref) > 100) {
        $errors[] = '地域は必須で、100文字以内で入力してください。';
    }

    // 写真（ファイル）の処理と検査
    $attachment = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($file['size'] > $maxSize) {
                $errors[] = 'ファイルサイズは2MB以下にしてください。';
            } else {
                // MIME タイプチェック
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/pjpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                ];

                if (!array_key_exists($mime, $allowed)) {
                    $errors[] = '許可されていないファイル形式です（jpg/png/gif のみ）。';
                } else {
                    $contents = file_get_contents($file['tmp_name']);
                    if ($contents === false) {
                        $errors[] = 'ファイルの読み込みに失敗しました。';
                    } else {
                        $attachment = [
                            'filename' => 'photo.' . $allowed[$mime],
                            'mime'     => $mime,
                            'content'  => chunk_split(base64_encode($contents)),
                        ];
                    }
                }
            }
        } else {
            $errors[] = 'ファイルアップロードでエラーが発生しました。';
        }
    }

    if (empty($errors)) {
        // ヘッダーインジェクション対策：改行を除去
        $email_sanitized = str_replace(["\r", "\n"], '', $email);

        // メールヘッダーは固定 From と Reply-To を利用
        $from = 'Fenex Agency <no-reply@fenex.example>';
        $replyTo = $email_sanitized;

        $boundary = md5(uniqid(mt_rand(), true));

        $headers  = "From: {$from}\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        // 本文組立
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= "エントリーがありました！\n\n";
        $body .= "お名前：" . $name . "\n";
        $body .= "年齢：" . $age . "\n";
        $body .= "地域：" . $pref . "\n";
        $body .= "メール：" . $email . "\n\n";

        // 添付ファイルがあれば追加
        if ($attachment !== null) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: {$attachment['mime']}; name=\"{$attachment['filename']}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attachment['filename']}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $attachment['content'] . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $subject_encoded = mb_encode_mimeheader($subject, 'UTF-8');

        // 送信
        if (mail($to, $subject_encoded, $body, $headers)) {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            echo "<h1>送信完了！</h1><p>ありがとうございます、{$safeName}。送信が完了しました。</p>";
        } else {
            error_log("Mail send failed for entry from {$email_sanitized}");
            echo "<h1>送信エラー</h1><p>送信中にエラーが発生しました。時間をおいて再度お試しください。</p>";
        }

    } else {
        // エラーを表示（HTMLエスケープ）
        echo "<h1>入力エラー</h1><ul>";
        foreach ($errors as $e) {
            echo "<li>" . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        echo "</ul>";
    }
}
?>