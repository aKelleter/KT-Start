<?php
declare(strict_types=1);

namespace App\Service;

final class UrlMetaService
{
    /** @return array{title: string, host: string, description: string} */
    public static function fetch(string $url): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KT-Start/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $html = curl_exec($ch);
        curl_close($ch);

        if (!is_string($html) || $html === '') {
            return ['title' => '', 'host' => $host, 'description' => ''];
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $title       = self::extractTitle($dom);
        $description = self::extractDescription($dom);

        return [
            'title'       => $title,
            'host'        => $host,
            'description' => $description,
        ];
    }

    private static function extractTitle(\DOMDocument $dom): string
    {
        // og:title first
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (
                strtolower((string) $meta->getAttribute('property')) === 'og:title'
                && $meta->getAttribute('content') !== ''
            ) {
                return trim((string) $meta->getAttribute('content'));
            }
        }

        // <title> tag
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            return trim((string) $titles->item(0)->textContent);
        }

        return '';
    }

    private static function extractDescription(\DOMDocument $dom): string
    {
        $metas = $dom->getElementsByTagName('meta');

        // og:description first
        foreach ($metas as $meta) {
            if (
                strtolower((string) $meta->getAttribute('property')) === 'og:description'
                && $meta->getAttribute('content') !== ''
            ) {
                return trim((string) $meta->getAttribute('content'));
            }
        }

        // meta name="description"
        foreach ($metas as $meta) {
            if (
                strtolower((string) $meta->getAttribute('name')) === 'description'
                && $meta->getAttribute('content') !== ''
            ) {
                return trim((string) $meta->getAttribute('content'));
            }
        }

        return '';
    }
}
