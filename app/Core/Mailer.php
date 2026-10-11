<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Dependency-free mailer. Drivers:
 *  - log  : writes the email to storage/logs/mail-*.log (local/dev)
 *  - smtp : authenticated SMTP with STARTTLS/SSL (Hostinger, Zoho, SES SMTP, etc.)
 * Sending never throws to the caller – a failed email must not break checkout.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $cfg = config('mail');
        try {
            if ($cfg['driver'] === 'smtp') {
                self::smtp($cfg, $to, $subject, $html);
            } else {
                $dir = BASE_PATH . '/storage/logs';
                @file_put_contents("$dir/mail-" . date('Y-m-d') . '.log', "==== " . date('c') . " To: $to | $subject\n" . strip_tags($html) . "\n\n", FILE_APPEND);
            }
            return true;
        } catch (Throwable $e) {
            logger()->error('Mail failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
            return false;
        }
    }

    /**
     * Render an email template from app/Views/emails inside the email layout.
     * In web requests the email is sent AFTER the HTTP response has been flushed to the browser
     * (public/index.php calls fastcgi_finish_request), so a slow SMTP server never slows checkout.
     */
    public static function sendView(string $to, string $subject, string $template, array $data = []): bool
    {
        $html = View::render('emails/' . $template, $data + ['subject' => $subject], 'emails/layout');
        if (PHP_SAPI !== 'cli') {
            register_shutdown_function(static fn () => self::send($to, $subject, $html));
            return true;
        }
        return self::send($to, $subject, $html);
    }

    private static function smtp(array $cfg, string $to, string $subject, string $html): void
    {
        $host = ($cfg['encryption'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
        $fp = @stream_socket_client($host . ':' . $cfg['port'], $errno, $errstr, 15);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: $errstr");
        }
        stream_set_timeout($fp, 15);
        $read = function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        // $label is what gets logged on failure – never the raw command (AUTH lines carry credentials).
        $cmd = function (string $command, array $expect, ?string $label = null) use ($fp, $read): string {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
                throw new RuntimeException('SMTP error after ' . ($label ?? explode(' ', $command)[0]) . ': ' . trim($resp));
            }
            return $resp;
        };

        $read();
        $ehloHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $cmd("EHLO $ehloHost", [250]);
        if ($cfg['encryption'] === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS failed');
            }
            $cmd("EHLO $ehloHost", [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($cfg['username']), [334], 'AUTH username');
        $cmd(base64_encode(str_replace(' ', '', $cfg['password'])), [235], 'AUTH password');
        $cmd('MAIL FROM:<' . $cfg['from']['address'] . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);

        $boundary = bin2hex(random_bytes(8));
        $headers = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($cfg['from']['name']) . '?= <' . $cfg['from']['address'] . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode(html_entity_decode(strip_tags($html)))) . "\r\n"
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n--$boundary--";
        $cmd(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
    }
}
