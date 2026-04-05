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
        $settings = new SettingsRepository();

        // Si la case "Utiliser le proxy" est décochée, on n'applique pas le proxy
        if ($settings->get('check_proxy_enabled') === '0') {
            return '';
        }

        // Priorité : DB → .env → vide
        $fromDb  = $settings->get('check_proxy');
        $fromEnv = trim((string) ($_ENV['CHECK_PROXY'] ?? ''));
        return $fromDb !== '' ? $fromDb : $fromEnv;
    }

    /**
     * Suit les redirections et retourne l'URL finale.
     * Retourne null si l'URL ne redirige pas ou en cas d'erreur.
     */
    public static function getFinalUrl(string $url): ?string
    {
        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
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
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error    = curl_errno($ch);
        curl_close($ch);

        if ($error !== 0 || $finalUrl === '' || $finalUrl === $url) {
            return null;
        }

        return $finalUrl;
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
