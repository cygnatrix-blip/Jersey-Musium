<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** cURL JSON client used by payment gateways. */
final class HttpClient
{
    /** @return array{status:int, body:array, raw:string} */
    public static function request(string $method, string $url, array $headers = [], ?array $json = null, ?string $basicAuth = null, int $timeout = 30): array
    {
        $ch = curl_init($url);
        $h = ['Accept: application/json'];
        foreach ($headers as $k => $v) {
            $h[] = "$k: $v";
        }
        $opts = [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_SLASHES);
            $h[] = 'Content-Type: application/json';
        }
        if (($ca = (string) env('HTTP_CA_BUNDLE', '')) !== '' && is_file($ca)) {
            $opts[CURLOPT_CAINFO] = $ca; // explicit CA bundle for hosts whose PHP lacks one
        }
        if ($basicAuth !== null) {
            $opts[CURLOPT_USERPWD] = $basicAuth;
        }
        $opts[CURLOPT_HTTPHEADER] = $h;
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            throw new RuntimeException('HTTP request failed: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return ['status' => $status, 'body' => json_decode((string) $raw, true) ?: [], 'raw' => (string) $raw];
    }
}
