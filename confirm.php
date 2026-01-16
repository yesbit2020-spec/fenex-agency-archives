<?php
// --- 設定：ここにいとーちゃんのメールアドレスを入れてね ---
$to = "yesbit2020+agency@gmail.com"; 
$subject = "【Fenex Agency】AI診断エントリー届いたよっ！";

// 日本語メールの設定
mb_language("Japanese");
mb_internal_encoding("UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $errors = [];

    // 初期デバッグログ：POST を受信したら必ず記録（検証エラーでメール処理に到達しない場合の調査用）
    $logfile = __DIR__ . '/mail_debug.log';
    $post_json = json_encode($_POST, JSON_UNESCAPED_UNICODE);
    $files_summary = [];
    foreach ($_FILES as $fk => $fv) {
        $files_summary[$fk] = ['name' => $fv['name'] ?? '', 'size' => $fv['size'] ?? 0, 'error' => $fv['error'] ?? 'no'];
    }
    $files_json = json_encode($files_summary);
    $entry = date('c') . " | POST received | script=" . __FILE__ . " | post=" . $post_json . " | files=" . $files_json . "\n";
    $written = @file_put_contents($logfile, $entry, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        error_log('[mail-debug] initial write FAILED');
    } else {
        error_log('[mail-debug] initial write OK');
    }

    // mail_full_debug.log のテスト書き込み（ファイル作成と権限確認用）
    $mail_full = __DIR__ . '/mail_full_debug.log';
    $test_entry = date('c') . " | mail_full test write\n";
    $res_full = @file_put_contents($mail_full, $test_entry, FILE_APPEND | LOCK_EX);
    if ($res_full === false) {
        // 書き込み失敗時は詳細をエラーログに残す（@ を使うがエラー情報を取得）
        $err = error_get_last();
        error_log('[mail-debug] mail_full write FAILED: ' . ($err['message'] ?? 'no details'));
    } else {
        error_log('[mail-debug] mail_full write OK: ' . $res_full);
    }

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
            $maxSize = 10 * 1024 * 1024; // 10MB
                      if ($file['size'] > $maxSize) {
                $errors[] = 'ファイルサイズは10MB以下にしてください。';
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

        // 差出人アドレスをサーバのドメインに合わせて設定（fenex.example のままだと Gmail に弾かれる可能性が高い）
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : gethostname();
        $server_name = preg_replace('/[^a-z0-9.\-]/i', '', $server_name);
        if ($server_name === '') { $server_name = 'localhost'; }
        $from_address = 'no-reply@' . $server_name;
        $from = 'Fenex Agency <' . $from_address . '>';
        $replyTo = $email_sanitized;

        // === TEST ONLY: 一時的にエンベロープ送信者を override してバウンス確認 ===
        // テストが終わったらこのブロックを削除または $test_envelope を false にしてください。
        $test_envelope = false; // set to false to disable
        if ($test_envelope) {
            $override_envelope = 'kentaro-itou@live.jp';
            $from_address = $override_envelope; // Return-Path に使われる
            $from = 'Fenex Agency <' . $from_address . '>';
            // ログに記録
            @file_put_contents($logfile, date('c') . " | envelope_override={$from_address}\n", FILE_APPEND | LOCK_EX);
            error_log('[mail-debug] envelope_override=' . $from_address);
        }

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

        // プレーンテキスト本文（SMTP利用時はこちらを使う）
        $plain_body  = "エントリーがありました！\n\n";
        $plain_body .= "お名前：" . $name . "\n";
        $plain_body .= "年齢：" . $age . "\n";
        $plain_body .= "地域：" . $pref . "\n";
        $plain_body .= "メール：" . $email . "\n\n";

        // 送信前の完全なメールダンプ（添付のbase64は省略）を保存
        $mail_dump_file = __DIR__ . '/mail_full_debug.log';
        $safe_body = $body;
        // 添付の base64 部分を省略してダンプ（boundary の直前までを置換）
        $safe_body = preg_replace('/Content-Transfer-Encoding: base64\\r\\n\\r\\n.*?(?=\\r\\n--' . preg_quote($boundary, '/') . ')/s', "Content-Transfer-Encoding: base64\\r\\n\\r\\n<BASE64_CONTENT_OMITTED>", $safe_body);
        // 上限を付けて大きすぎるダンプを防ぐ
        if (strlen($safe_body) > 20000) { $safe_body = substr($safe_body, 0, 20000) . "\n---TRUNCATED---\n"; }
        $dump = date('c') . " | mail dump\nSubject: {$subject_encoded}\nHeaders:\n{$headers}\n\nBody:\n" . $safe_body . "\n\n";
        @file_put_contents($mail_dump_file, $dump, FILE_APPEND | LOCK_EX);
        error_log('[mail-debug] mail dump written to ' . $mail_dump_file);

        // --- SMTP 設定（PHPMailer を直接導入して利用） ---
        // パスワードは外部ファイル（smtp_credentials.php）に置くことを推奨します。
        $use_smtp = true; // SMTP を有効化（info@fenex.jp のアカウントを使用）
        $smtp_config = [
            'host' => 'smtp.heteml.jp',
            'port' => 465,
            'username' => 'info@fenex.jp',
            'password' => '', // 後で smtp_credentials.php かここにパスワードを設定してください
            'encryption' => 'ssl',
            'from_address' => 'info@fenex.jp',
            'from_name' => 'Fenex Agency'
        ];
        // 認証情報は外部ファイルに分離して管理（例: __DIR__ . '/smtp_credentials.php' が ['password'=>'...'] を返す）
        // --- 認証情報の読み込み ---
        $creds_file = __DIR__ . '/smtp_credentials.php';
        if (file_exists($creds_file)) {
            require_once $creds_file; // ファイルを読み込む（defineを実行する）
            
            // もし定数 SMTP_PASS が定義されていたら、それを使う
            if (defined('SMTP_PASS')) {
                $smtp_config['password'] = SMTP_PASS;
            }
            // もし定数 SMTP_USER が定義されていたら、ユーザー名も上書きする
            if (defined('SMTP_USER')) {
                $smtp_config['username'] = SMTP_USER;
            }
        }

        // PHPMailer の読み込み（composer/autoload を優先、無ければプロジェクト内の PHPMailer/src を期待）
        $phpmailer_loaded = false;
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            $phpmailer_loaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
        } else {
            $pmPath = __DIR__ . '/PHPMailer/src/';
            if (file_exists($pmPath . 'PHPMailer.php')) {
                require_once $pmPath . 'Exception.php';
                require_once $pmPath . 'PHPMailer.php';
                require_once $pmPath . 'SMTP.php';
                $phpmailer_loaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
            }
        }
        if (!$phpmailer_loaded) {
            error_log('[mail-debug] PHPMailer not found. Place PHPMailer in "PHPMailer/src" or run composer require phpmailer/phpmailer');
            @file_put_contents($logfile, date('c') . " | smtp error: PHPMailer not installed\n", FILE_APPEND | LOCK_EX);
        }

        $sent = false;

        if ($use_smtp) {
            // PHPMailer による SMTP 送信
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                error_log('[mail-debug] PHPMailer not found. Install via: composer require phpmailer/phpmailer');
                @file_put_contents($logfile, date('c') . " | smtp error: PHPMailer not installed\n", FILE_APPEND | LOCK_EX);
            } else {
                try {
                    // 名前空間のバックスラッシュを全部取ったわよ！
                    $mail = new PHPMailer(true); 
                    $mail->CharSet = 'UTF-8';
                    $mail->isSMTP();
                    $mail->Host = $smtp_config['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtp_config['username'];
                    $mail->Password = $smtp_config['password'];
                    if (!empty($smtp_config['encryption'])) { $mail->SMTPSecure = $smtp_config['encryption']; }
                    $mail->Port = $smtp_config['port'];

                    $mail->setFrom($smtp_config['from_address'], $smtp_config['from_name']);
                    $mail->addAddress($to);
                    if (!empty($replyTo)) { $mail->addReplyTo($replyTo); }

                    $mail->Subject = $subject;
                    $mail->Body    = $plain_body;
                    $mail->AltBody = $plain_body;
                    $mail->isHTML(false);

                    if ($attachment !== null) {
                        $b64 = preg_replace('/\s+/', '', $attachment['content']);
                        $data = base64_decode($b64);
                        $mail->addStringAttachment($data, $attachment['filename'], 'base64', $attachment['mime']);
                    }

              $mail->send();
                    $sent = true;
                    error_log("[mail-debug] SMTP send OK to {$to}");
                } catch (Exception $e) {
                    $err = $mail->ErrorInfo ?? $e->getMessage();
                    error_log("[mail-debug] SMTP send FAILED: " . $err);
                    $sent = false;
                }
            }
        }

            

        // SMTP 無効／SMTP 送信失敗時は既存の mail() を利用（フォールバック）
        if (!$sent) {
            $additional_parameters = '-f' . $from_address;

            // デバッグ情報（エラーログへ）
            $ini_info = sprintf("sendmail_path=%s, SMTP=%s, smtp_port=%s", ini_get('sendmail_path'), ini_get('SMTP'), ini_get('smtp_port'));
            error_log("[mail-debug] ini: {$ini_info}");
            error_log("[mail-debug] to={$to} subject={$subject_encoded} from={$from_address} replyTo={$replyTo} envelope={$additional_parameters}");

            // 可能なら詳細ログ（ファイル）にも追記
            $logfile = __DIR__ . '/mail_debug.log';
            $logentry = date('c') . " | to={$to} | from={$from_address} | replyTo={$replyTo} | envelope={$additional_parameters} | sendmail_path=" . ini_get('sendmail_path') . "\n";
            @file_put_contents($logfile, $logentry, FILE_APPEND | LOCK_EX);

            $sent = mail($to, $subject_encoded, $body, $headers, $additional_parameters);

            if ($sent) {
                error_log("[mail-debug] Mail sent OK to {$to}");
                @file_put_contents($logfile, date('c') . " | sent OK to={$to}\n", FILE_APPEND | LOCK_EX);
            } else {
                error_log("[mail-debug] Mail send FAILED for entry from {$email_sanitized}");
                @file_put_contents($logfile, date('c') . " | send FAILED to={$to}\n", FILE_APPEND | LOCK_EX);
            }
        }

        if ($sent) {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            echo "<h1>送信完了！</h1><p>ありがとうございます、{$safeName}。送信が完了しました。</p>";
        } else {
            echo "<h1>送信エラー</h1><p>送信中にエラーが発生しました。管理者へ連絡するか、サーバのメール設定（sendmail/postfix、SMTP）を確認してください。</p>";
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