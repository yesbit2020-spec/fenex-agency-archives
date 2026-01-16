<?php
// --- 設定：宛先メールアドレス ---
$to = "yesbit2020+agency@gmail.com"; 
$subject = "【Fenex Agency】AI診断エントリー届いたよっ！";

mb_language("Japanese");
mb_internal_encoding("UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $errors = [];
    $logfile = __DIR__ . '/mail_debug.log';

    // 入力取得
    $name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $age   = isset($_POST['age'])   ? trim($_POST['age'])   : '';
    $pref  = isset($_POST['pref'])  ? trim($_POST['pref'])  : '';
    $area  = isset($_POST['area'])  ? trim($_POST['area'])  : '';

    // バリデーション
    if ($name === '') { $errors[] = 'NAMEは必須です。'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = '有効なメールアドレスが必要です。'; }
    if ($pref === '') { $errors[] = '都道府県を選択してください。'; }

    // 写真処理（2枚分ループで回すわよ）
    $attachments = [];
    $file_fields = ['photo_upper' => '上半身', 'photo_full' => '全身'];

    foreach ($file_fields as $field => $label) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $errors[] = "{$label}写真のサイズが大きすぎます（10MBまで）。";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $allowed = ['image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
                if (!array_key_exists($mime, $allowed)) {
                    $errors[] = "{$label}写真は許可されていない形式です。";
                } else {
                    $attachments[] = [
                        'filename' => "{$field}." . $allowed[$mime],
                        'mime'     => $mime,
                        'content'  => file_get_contents($file['tmp_name'])
                    ];
                }
            }
        } else {
            $errors[] = "{$label}写真をアップロードしてください。";
        }
    }

    if (empty($errors)) {
        // SMTP設定（さっき成功したGmail直通ルートよ！）
        $smtp_config = [
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'username' => 'yesbit2020@gmail.com',
            'password' => 'sbjytndatqyohwsq ', // ※必ず書き換えて！
            'encryption' => 'ssl'
        ];

        // 本文作成
        $plain_body  = "AI診断エントリーがありました！\n\n";
        $plain_body .= "【NAME】" . $name . "\n";
        $plain_body .= "【年齢】" . $age . "\n";
        $plain_body .= "【都道府県】" . $pref . "\n";
        $plain_body .= "【活動可能範囲】" . $area . "\n";
        $plain_body .= "【メール】" . $email . "\n";

        // PHPMailer読み込み
        $pmPath = __DIR__ . '/PHPMailer/src/';
        require_once $pmPath . 'Exception.php';
        require_once $pmPath . 'PHPMailer.php';
        require_once $pmPath . 'SMTP.php';

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

            $mail->setFrom($smtp_config['username'], 'Fenex Agency');
            $mail->addAddress($to);
            $mail->addReplyTo($email);

            $mail->Subject = $subject;
            $mail->Body    = $plain_body;

            // 添付ファイルを2枚分追加
            foreach ($attachments as $at) {
                $mail->addStringAttachment($at['content'], $at['filename'], 'base64', $at['mime']);
            }

            $mail->send();
            $sent = true;
        } catch (Exception $e) {
            $sent = false;
            error_log("SMTP Error: " . $mail->ErrorInfo);
        }

        if ($sent) {
            echo "<h1>送信完了！</h1><p>ありがとうございます、{$name}様。エントリーを受け付けました。</p>";
            echo '<p><a href="index.html">ホームへ戻る</a></p>';
        } else {
            echo "<h1>送信エラー</h1><p>エラーが発生しました。ログを確認してください。</p>";
        }
    } else {
        echo "<h1>入力エラー</h1><ul>";
        foreach ($errors as $e) { echo "<li>" . htmlspecialchars($e) . "</li>"; }
        echo "</ul><p><a href='javascript:history.back()'>戻って修正する</a></p>";
    }
}
?>