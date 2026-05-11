<?php
/**
 * Professional Webhook Handler with .env support
 * Supports GitHub SHA256 Signature Verification
 */

// 1. .env dosyasını oku ve değişkenleri tanımla
function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Yorum satırlarını atla
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
    return true;
}

loadEnv(__DIR__ . '/.env');

$secret = $_ENV['WEBHOOK_SECRET'] ?? null;
$branch = $_ENV['GIT_BRANCH'] ?? 'main';
$repo_dir = __DIR__; // PHP dosyasının olduğu dizin

// 2. Güvenlik Kontrolleri
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (!$secret || !$signature) {
    http_response_code(403);
    die("Yetkisiz erişim veya eksik yapılandırma.");
}

// GitHub imzasını doğrula
$payload = file_get_contents('php://input');
$expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected_signature, $signature)) {
    http_response_code(403);
    die("Geçersiz imza. Şifre uyuşmuyor.");
}

// 3. Git Pull İşlemi
// CloudPanel'de PHP ilgili site kullanıcısı ile çalışır.
// Bu yüzden kullanıcının SSH anahtarı GitHub'da ekli olmalıdır.
$command = "cd " . escapeshellarg($repo_dir) . " && git pull origin " . escapeshellarg($branch) . " 2>&1";
$output = shell_exec($command);

// 4. Loglama (Hata takibi için)
$log_entry = date('[Y-m-d H:i:s]') . " Branch: $branch \nOutput: $output\n" . str_repeat('-', 40) . "\n";
file_put_contents($repo_dir . '/webhook_deploy.log', $log_entry, FILE_APPEND);

echo "Başarıyla güncellendi!";
