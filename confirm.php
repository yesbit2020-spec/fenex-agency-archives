<?php
// --- 設定：ここにいとーちゃんのメールアドレスを入れてね ---
$to = "yesbit2020+agency@gmail.com"; 
$subject = "【Fenex Agency】AI診断エントリー届いたよっ！";

// 日本語メールの設定
mb_language("Japanese");
mb_internal_encoding("UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $errors = [];

    // デバッグ用ログファイルの準備
    $logfile = __DIR__ . '/mail_debug.log';
    $post_json = json_encode($_POST, JSON_UNESCAPED_UNICODE);
    $files_summary = [];
    foreach ($_FILES as $fk => $fv) {
        $files_summary[$fk] = ['name' => $fv['name'] ?? '', 'size' => $fv['size'] ?? 0, 'error' => $fv['error'] ?? 'no'];
    }
    $files_json = json_encode($files_summary);
    $entry = date('c') . " | POST received | post=" . $post_json . " | files=" . $files_json . "\n";
    @file_put_contents($logfile, $entry, FILE_APPEND | LOCK_EX);

    // 入力取得
    $name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $age   = isset($_POST['age'])   ? trim($_POST['age'])   : '';
    $pref  = isset($_POST['pref'])  ? trim($_POST['pref'])  : '';

    // バリデーション
    if ($name === '') { $errors[] = 'お名前は必須です。'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = '有効なメールアドレスを入力してください。'; }
    if ($pref === '') { $errors[] = '地域は必須です。'; }

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
        // メールの差出人設定
        $email_sanitized = str_replace(["\r", "\n"], '', $email);
        $from_address = 'info@fenex.jp';
        $from_name = 'Fenex Agency';
        $replyTo = $email_sanitized;

        // 本文作成
        $plain_body  = "エントリーがありました！\n\n";
        $plain_body .= "お名前：" . $name . "\n";
        $plain_body .= "年齢：" . $age . "\n";
        $plain_body .= "地域：" . $pref . "\n";
        $plain_body .= "メール：" . $email . "\n\n";

        // --- SMTP 設定（今度はGmailの直通ルートで行くわよ！） ---
        $smtp_config = [
            'host' => 'smtp.gmail.com',  // ヘテムルじゃなくてGmailに変える！
            'port' => 465,               // ポートは465
            'username' => 'yesbit2020＋agency@gmail.com', // いとーちゃんのGmailアドレス
            'password' => 'sbjytndatqyohwsq', 
            'encryption' => 'ssl'
        ];

        // 魔法の合鍵（パスワード）読み込み
        $creds_file = __DIR__ . '/smtp_credentials.php';
        if (file_exists($creds_file)) {
            require_once $creds_file;
            if (defined('SMTP_PASS')) { $smtp_config['password'] = SMTP_PASS; }
            if (defined('SMTP_USER')) { $smtp_config['username'] = SMTP_USER; }
        }

        // --- PHPMailer 読み込み（いとーちゃんの手作業階層に合わせる） ---
        $pmPath = __DIR__ . '/PHPMailer/src/';
        $sent = false;

        if (file_exists($pmPath . 'PHPMailer.php')) {
            require_once $pmPath . 'Exception.php';
            require_once $pmPath . 'PHPMailer.php';
            require_once $pmPath . 'SMTP.php';

            // 名前空間を使って呼び出し
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host       = $smtp_config['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp_config['username'];
                $mail->Password   = $smtp_config['password'];
                $mail->SMTPSecure = $smtp_config['encryption'];
                $mail->Port       = $smtp_config['port'];

                $mail->setFrom($from_address, $from_name);
                $mail->addAddress($to);
                $mail->addReplyTo($replyTo);

                $mail->Subject = $subject;
                $mail->Body    = $plain_body;

                if ($attachment !== null) {
                    $data = base64_decode($attachment['content']);
                    $mail->addStringAttachment($data, $attachment['filename'], 'base64', $attachment['mime']);
                }

                $mail->send();
                $sent = true;
                @file_put_contents($logfile, date('c') . " | SMTP send OK\n", FILE_APPEND);
            } catch (Exception $e) {
                // エラー時はフォールバックせずにログを残して終了
                $errMsg = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
                @file_put_contents($logfile, date('c') . " | SMTP send FAILED: " . $errMsg . "\n", FILE_APPEND);
            }
        } else {
            @file_put_contents($logfile, date('c') . " | PHPMailer files not found in " . $pmPath . "\n", FILE_APPEND);
        }

        // 送信結果の表示
        if ($sent) {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            echo "<h1>送信完了！</h1><p>ありがとうございます、{$safeName}。送信が完了しました。</p>";
        } else {
            echo "<h1>送信エラー</h1><p>送信に失敗しました。管理者へ連絡してください。</p>";
        }

    } else {
        // 入力エラーの表示
        echo "<h1>入力エラー</h1><ul>";
        foreach ($errors as $e) { echo "<li>" . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . "</li>"; }
        echo "</ul>";
    }
}
?>