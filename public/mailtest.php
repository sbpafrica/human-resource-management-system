<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
set_time_limit(60);

echo "<h2>Hardcoded Password Test</h2>";
flush();

$laravelRoot = realpath(__DIR__ . '/..');
require $laravelRoot . '/vendor/autoload.php';

$host       = 'smtp.gmail.com';
$port       = 587;
$username   = 'sbpafricait@gmail.com';
$password   = 'pueh badg kome slzk';   // ← exactly as in your working PHPMailer
$encryption = 'tls';
$from       = 'sbpafricait@gmail.com';

echo "<pre>";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Username: $username\n";
echo "Password: $password\n";
echo "Encryption: $encryption\n";
echo "</pre>";
flush();

try {
    $dsn = sprintf(
        'smtp://%s:%s@%s:%d?encryption=tls',
        urlencode($username),
        urlencode($password),
        $host,
        $port
    );

    $transport = Symfony\Component\Mailer\Transport::fromDsn($dsn);
    $mailer = new Symfony\Component\Mailer\Mailer($transport);

    $email = (new Symfony\Component\Mime\Email())
        ->from($from)
        ->to('kadisi290@gmail.com')
        ->subject('Hardcoded Password Test - ' . date('Y-m-d H:i:s'))
        ->text('Testing with hardcoded password that works in PHPMailer.');

    echo "<h3>Sending...</h3>";
    flush();

    $mailer->send($email);

    echo "<h3 style='color:green; font-size:22px;'>✓ SUCCESS! Email sent.</h3>";

} catch (Throwable $e) {
    echo "<h3 style='color:red;'>FAILED:</h3>";
    echo "<pre style='background:#fee; padding:15px;'>";
    echo $e->getMessage();
    echo "</pre>";
}