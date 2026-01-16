<?php
// --- 設定：ここにいとーちゃんのメールアドレスを入れてね ---
$to = "yesbit2020+agency@gmail.com"; 
$subject = "【Fenex Agency】AI診断エントリー届いたよっ！";

// 日本語メールの設定
mb_language("Japanese");
mb_internal_encoding("UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $errors = [];

    // 初期デバッグログ
    $logfile = __DIR__ . '/mail_debug.log';
    $post_json = json_encode($_POST, JSON_UNESCAPED_UNICODE);
    $files_summary = [];
    foreach ($_FILES as $fk => $fv) {
        $files_summary[$fk] = ['name' => $fv['name'] ?? '', 'size' => $fv['size'] ?? 0, 'error' => $fv['error'] ?? 'no'];
    }
    $files_json = json_encode($files_summary);
    $entry = date('c') . " | POST received | script=" . __FILE__ . " | post=" . $post_json . " | files=" . $files_json . "\n";
    @file_put_contents($logfile, $entry, FILE_APPEND | LOCK_EX);

    // 入力取得とサニタイズ
    $name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $age   = isset($_POST['age'])   ? trim($_POST['age'])   : '';
    $pref  = isset($_POST['pref'])  ? trim($_POST['pref'])  : '';

    // バリデーション
    if ($name === '' || mb_strlen($name) > 100) { $errors[] = 'お名前は必須です。'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = '有効なメールアドレスを入力してください。'; }
    if ($pref === '' || mb_strlen($pref) > 100) { $errors[] = '地域は必須です。'; }

    // 写真（ファイル）の処理
    $attachment = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                $errors[] = 'ファイルサイズは10MB以下にしてください。';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $allowed = ['image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
                if (!array_key_exists($mime, $allowed)) {
                    $errors[] = '許可されていない形式です。';
                } else {
                    $contents = file_get_contents($file['tmp_name']);
                    $attachment = [
                        'filename' => 'photo.' . $allowed[$mime],
                        'mime'     => $mime,
                        'content'  => chunk_split(base64_encode($contents)),
                    ];
                }
            }
        }
    }

    if (empty($errors)) {
        $email_sanitized = str_replace(["\r", "\n"], '', $email);
        $from_address = 'no-reply@fenex.jp';
        $from = 'Fenex Agency <' . $from_address . '>';
        $replyTo = $email_sanitized;

        $subject_encoded = mb_encode_mimeheader($subject, 'UTF-8');
        $plain_body  = "エントリーがありました！\n\n";
        $plain_body .= "お名前：" . $name . "\n";
        $plain_body .= "年齢：" . $age . "\n";
        $plain_body .= "地域：" . $pref . "\n";
        $plain_body .= "メール：" . $email . "\n\n";

        // --- SMTP 設定 ---
        $use_smtp = true;
        $smtp_config = [
            'host' => 'smtp.heteml.jp',
            'port' => 465,
            'username' => 'info@fenex.jp',
            'password' => '',
            'encryption' => 'ssl',
            'from_address' => 'info@fenex.jp',
            'from_name' => 'Fenex Agency'
        ];

        // 外部ファイルの読み込み
        $creds_file = __DIR__ . '/smtp_credentials.php';
        if (file_exists($creds_file)) {
            require_once $creds_file;
            if (defined('SMTP_PASS')) { $smtp_config['password'] = SMTP_PASS; }
            if (defined('SMTP_USER')) { $smtp_config['username'] = SMTP_USER; }
        }

        // PHPMailer の読み込み（手作業アップロード階層に合わせました）
        $pmPath = __DIR__ . '/PHPMailer/src/';
        if (file_exists($pmPath . 'PHPMailer.php')) {
            require_once $pmPath . 'Exception.php';
            require_once $pmPath . 'PHPMailer.php';
            require_once $pmPath . 'SMTP.php';
        }

        $sent = false;
        if ($use_smtp && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host = $smtp_config['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_config['username'];
                $mail->Password = $smtp_config['password'];
                $mail->SMTPSecure = $smtp_config['encryption'];
                $mail->Port = $smtp_config['port'];

                $mail->setFrom($smtp_config['from_address'], $smtp_config['from_name']);
                $mail->addAddress($to);
                $mail->addReplyTo($replyTo);

                $mail->Subject = $subject;
                $mail->Body    = $plain_body;
                $mail->isHTML(false);

                if ($attachment !== null) {
                    $b64 = preg_replace('/\s+/', '', $attachment['content']);
                    $data = base64_decode($b64);
                    $mail->addStringAttachment($data, $attachment['filename'], 'base64', $attachment['mime']);
                }

                $mail->send();
                $sent = true;
                @file_put_contents($logfile, date('c') . " | SMTP sent OK\n", FILE_APPEND);
            } catch (Exception $e) {
                error_log("[mail-debug] SMTP FAILED: " . $mail->ErrorInfo);
                @file_put_contents($logfile, date('c') . " | SMTP FAILED: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            }
        }

        // 送信結果の表示
        if ($sent) {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            echo "<h1>送信完了！</h1><p>ありがとうございます、{$safeName}。送信が完了しました。</p>";
        } else {
            echo "<h1>送信エラー</h1><p>メールの送信に失敗しました。ログを確認してください。</p>";
        }

    } else {
        echo "<h1>入力エラー</h1><ul>";
        foreach ($errors as $e) { echo "<li>" . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . "</li>"; }
        echo "</ul>";
    }
}
?>