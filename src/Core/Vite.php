<?php

namespace QuietRent\Core;

/**
 * Reads the Vite manifest and outputs the correct script/link tags.
 * In dev mode (APP_ENV=local) it points to the Vite dev server.
 * In production it uses the built manifest from public/assets/.vite/manifest.json
 */
class Vite
{
    private static ?array $manifest = null;

    public static function tags(string $entry = 'src/main.js'): string
    {
        if (Env::get('APP_ENV', 'production') === 'local') {
            // Dev server
            $viteUrl = Env::get('VITE_URL', 'http://localhost:5173');
            return <<<HTML
                <script type="module" src="{$viteUrl}/@vite/client"></script>
                <script type="module" src="{$viteUrl}/{$entry}"></script>
                HTML;
        }

        // Production: read manifest
        $manifest = self::manifest();
        $chunk    = $manifest[$entry] ?? null;
        if (!$chunk) {
            return '<!-- Vite manifest entry not found -->';
        }

        $tags = '';
        if (!empty($chunk['css'])) {
            foreach ($chunk['css'] as $css) {
                $tags .= '<link rel="stylesheet" href="/assets/' . $css . '">' . "\n";
            }
        }
        $tags .= '<script type="module" src="/assets/' . $chunk['file'] . '"></script>' . "\n";

        return $tags;
    }

    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = BASE_PATH . '/public/assets/.vite/manifest.json';
        if (!file_exists($path)) {
            return self::$manifest = [];
        }

        return self::$manifest = json_decode(file_get_contents($path), true) ?? [];
    }
}
