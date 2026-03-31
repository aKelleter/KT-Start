<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\SettingsRepository;

/**
 * Vérifie l'accessibilité d'une URL via cURL.
 *
 * Statuts retournés :
 *   'ok'       — HTTP 2xx
 *   'redirect' — HTTP 301 (redirection permanente — URL probablement déplacée)
 *   'error'    — HTTP 4xx / 5xx (ressource inaccessible ou erreur serveur)
 *   'timeout'  — Erreur réseau / timeout / host introuvable
 */
final class UrlCheckService
{
    private const TIMEOUT = 10;

    private static function proxy(): string
    {
        // Priorité : DB → .env → vide
        $fromDb  = (new SettingsRepository())->get('check_proxy');
        $fromEnv = trim((string) ($_ENV['CHECK_PROXY'] ?? ''));
        return $fromDb !== '' ? $fromDb : $fromEnv;
    }

    /**
     * @return array{status: 'ok'|'redirect'|'error'|'timeout', http_code: int}
     */
    public static function check(string $url): array
    {
        // Tentative HEAD d'abord (plus léger)
        $result = self::request($url, true);

        // Certains serveurs refusent HEAD (405) → fallback GET
        if ($result['http_code'] === 405) {
            $result = self::request($url, false);
        }

        return $result;
    }

    /**
     * @return array{status: 'ok'|'redirect'|'error'|'timeout', http_code: int}
     */
    private static function request(string $url, bool $headOnly): array
    {
        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => $headOnly,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KT-Start/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLINFO_HEADER_OUT    => true,
        ];

        $proxy = self::proxy();
        if ($proxy !== '') {
            $opts[CURLOPT_PROXY] = $proxy;
        }

        curl_setopt_array($ch, $opts);

        curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);

        // Détecter si une redirection 301 a eu lieu
        // On refait une requête sans suivre les redirections pour lire le premier code
        $firstCode = self::getFirstHttpCode($url, $headOnly);

        curl_close($ch);

        if ($curlError !== 0 || $httpCode === 0) {
            return ['status' => 'timeout', 'http_code' => 0];
        }

        $status = match (true) {
            $firstCode === 301                   => 'redirect',
            $httpCode >= 200 && $httpCode < 300  => 'ok',
            default                              => 'error',
        };

        return ['status' => $status, 'http_code' => $firstCode ?: $httpCode];
    }

    private static function getFirstHttpCode(string $url, bool $headOnly): int
    {
        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => $headOnly,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KT-Start/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        $proxy = self::proxy();
        if ($proxy !== '') {
            $opts[CURLOPT_PROXY] = $proxy;
        }

        curl_setopt_array($ch, $opts);

        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code;
    }
}
