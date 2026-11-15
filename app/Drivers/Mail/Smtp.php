<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Mail;

use App\Drivers\MailDriver;
use App\Drivers\Result;

class Smtp extends MailDriver
{
    public function name(): string
    {
        return 'SMTP 发信';
    }

    public function send(string $to, string $subject, string $html): Result
    {
        try {
            $this->deliver($to, $subject, $html);
            return Result::pass('邮件已投递');
        } catch (\Throwable $e) {
            return Result::fail('发信失败：' . $e->getMessage());
        }
    }

    private function deliver(string $to, string $subject, string $html): void
    {
        $host = (string) $this->cfg('host');
        $port = $this->int('port', 465);
        $encryption = (string) $this->cfg('encryption', 'ssl');
        if ($host === '') {
            throw new \RuntimeException('未配置 SMTP 服务器');
        }

        $scheme = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if ($fp === false) {
            throw new \RuntimeException('连接 ' . $host . ':' . $port . ' 失败（' . $errstr . '）');
        }
        stream_set_timeout($fp, 20);

        $this->expect($fp, '220');

        $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $hostname = preg_replace('/:\d+$/', '', $hostname) ?: 'localhost';
        $this->command($fp, 'EHLO ' . $hostname, '250');

        if ($encryption === 'tls') {
            $this->command($fp, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS 协商失败');
            }
            $this->command($fp, 'EHLO ' . $hostname, '250');
        }

        $user = (string) $this->cfg('user');
        $pass = (string) $this->cfg('pass');
        if ($user !== '') {
            $this->command($fp, 'AUTH LOGIN', '334');
            $this->command($fp, base64_encode($user), '334');
            $this->command($fp, base64_encode($pass), '235');
        }

        $from = (string) $this->cfg('from', $user);
        $fromName = (string) $this->cfg('from_name', '蜗牛图床');

        $this->command($fp, 'MAIL FROM:<' . $from . '>', '250');
        $this->command($fp, 'RCPT TO:<' . $to . '>', '250');
        $this->command($fp, 'DATA', '354');

        $headers = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $hostname . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        $body = chunk_split(base64_encode($html), 76, "\r\n");
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        fwrite($fp, $payload . "\r\n.\r\n");
        $this->expect($fp, '250');

        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
    }

    private function command($fp, string $line, string $expectCode): string
    {
        fwrite($fp, $line . "\r\n");
        return $this->expect($fp, $expectCode);
    }

    private function expect($fp, string $code): string
    {
        $response = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
            if (strlen($response) > 8192) {
                break;
            }
        }
        if ($response === '') {
            throw new \RuntimeException('服务器无响应');
        }
        if (strncmp(trim($response), $code, 3) !== 0) {
            throw new \RuntimeException(trim($response));
        }
        return $response;
    }
}
