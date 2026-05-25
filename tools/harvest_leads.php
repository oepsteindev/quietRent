<?php

/**
 * Tampa Bay Property Manager Lead Harvester
 *
 * Searches Google Places for property management companies in the Tampa Bay area,
 * scrapes their websites for email addresses, and outputs a CSV.
 *
 * Usage:
 *   php tools/harvest_leads.php            # fresh run, overwrites CSV
 *   php tools/harvest_leads.php --append   # adds new results without removing existing rows
 *
 * Requirements:
 *   - GOOGLE_PLACES_API_KEY in .env (or set as env var)
 *   - PHP with cURL enabled
 *
 * Output:
 *   tools/tampa_leads.csv
 */

chdir(dirname(__DIR__));

// ── Load .env ──────────────────────────────────────────────────────────────
if (file_exists('.env')) {
    foreach (file('.env') as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
}

$apiKey = $_ENV['GOOGLE_PLACES_API_KEY'] ?? getenv('GOOGLE_PLACES_API_KEY');
if (!$apiKey) {
    fwrite(STDERR, "ERROR: GOOGLE_PLACES_API_KEY not set in .env\n");
    exit(1);
}

define('API_KEY', $apiKey);
define('OUTPUT',  __DIR__ . '/tampa_leads.csv');
define('DELAY_MS', 1000); // ms between website scrapes (be polite)
define('FETCH_TIMEOUT', 8);

// ── Helpers ────────────────────────────────────────────────────────────────
function places_search(string $query, ?string $pageToken = null): array
{
    $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query([
        'query' => $query,
        'key'   => API_KEY,
        ...(  $pageToken ? ['pagetoken' => $pageToken] : []),
    ]);
    $raw = curl_get($url);
    return json_decode($raw, true) ?? [];
}

function place_details(string $placeId): array
{
    $url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
        'place_id' => $placeId,
        'fields'   => 'name,formatted_address,formatted_phone_number,website',
        'key'      => API_KEY,
    ]);
    $raw = curl_get($url);
    $data = json_decode($raw, true) ?? [];
    return $data['result'] ?? [];
}

function curl_get(string $url, int $timeout = 10): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; LeadBot/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: '';
}

function extract_emails(string $html, string $baseUrl): array
{
    $emails = [];

    // mailto: links
    preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $html, $m);
    foreach ($m[1] as $e) $emails[] = strtolower($e);

    // Plain-text email patterns
    preg_match_all('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/', $html, $m);
    foreach ($m[1] as $e) {
        $e = strtolower($e);
        // Filter out common non-person addresses and image/asset paths
        if (!preg_match('/\.(png|jpg|jpeg|gif|svg|webp|css|js)$/i', $e)) {
            $emails[] = $e;
        }
    }

    return array_unique(array_filter($emails, fn($e) => str_contains($e, '.')));
}

function scrape_site(string $url): array
{
    $emails = [];
    $pages  = [$url, rtrim($url, '/') . '/contact', rtrim($url, '/') . '/contact-us'];

    foreach ($pages as $page) {
        $html = curl_get($page, FETCH_TIMEOUT);
        if (!$html) continue;
        $emails = array_merge($emails, extract_emails($html, $url));
        usleep(300_000); // 300ms between pages on same site
    }

    return array_unique($emails);
}

function resolve_contact_url(string $baseUrl, string $html): ?string
{
    // Try to find a contact page link in the homepage HTML
    preg_match_all('/<a[^>]+href=["\']([^"\']*contact[^"\']*)["\']/', $html, $m, PREG_SET_ORDER);
    foreach ($m as $match) {
        $href = $match[1];
        if (str_starts_with($href, 'http')) return $href;
        if (str_starts_with($href, '/'))    return rtrim($baseUrl, '/') . $href;
    }
    return null;
}

// ── Args ───────────────────────────────────────────────────────────────────
$append = in_array('--append', $argv ?? []);

// ── Main ───────────────────────────────────────────────────────────────────
// Wide net across the Tampa Bay metro — each query targets a different city/area
// so Google returns different result sets with minimal overlap.
$queries = [
    'property management company Tampa FL',
    'property management company St. Petersburg FL',
    'property management company Clearwater FL',
    'property management company Brandon FL',
    'property management company Wesley Chapel FL',
    'property management company Sarasota FL',
    'property management company Lakeland FL',
    'property management company Bradenton FL',
    'residential property manager Tampa Bay FL',
    'rental property management Hillsborough County FL',
    'rental property management Pinellas County FL',
];

// Load existing place IDs from CSV so --append skips already-harvested companies
$placeIds         = [];
$existingWebsites = [];

if ($append && file_exists(OUTPUT)) {
    $efh    = fopen(OUTPUT, 'r');
    $header = fgetcsv($efh, 0, ',', '"', '\\');
    while ($row = fgetcsv($efh, 0, ',', '"', '\\')) {
        $r = array_combine($header, $row);
        if (!empty($r['website'])) $existingWebsites[$r['website']] = true;
    }
    fclose($efh);
    echo "Append mode: " . count($existingWebsites) . " existing entries loaded.\n";
}

$places = [];

echo "Searching Google Places…\n";

foreach ($queries as $query) {
    $pageToken = null;
    $pages = 0;
    do {
        if ($pageToken) sleep(2); // Google requires 2s before using next page token
        $res = places_search($query, $pageToken);

        if (empty($res['results'])) break;

        foreach ($res['results'] as $r) {
            if (!isset($placeIds[$r['place_id']])) {
                $placeIds[$r['place_id']] = true;
                $places[] = $r;
            }
        }


        $pageToken = $res['next_page_token'] ?? null;
        $pages++;
    } while ($pageToken && $pages < 3);

    echo "  Found " . count($places) . " unique places so far\n";
}

echo "\nFetching place details + scraping websites…\n";

$rows = [];
$done = 0;

foreach ($places as $p) {
    $done++;
    $detail = place_details($p['place_id']);
    $name    = $detail['name']                  ?? $p['name'] ?? '';
    $address = $detail['formatted_address']     ?? $p['formatted_address'] ?? '';
    $phone   = $detail['formatted_phone_number']?? '';
    $website = $detail['website']               ?? '';

    echo "  [{$done}/" . count($places) . "] {$name}";

    // Skip in append mode if we already have this website
    if ($append && $website && isset($existingWebsites[$website])) {
        echo " → already in CSV, skipping\n";
        continue;
    }

    $emails = [];
    if ($website) {
        $emails = scrape_site($website);
        echo " → " . count($emails) . " email(s) found";
    }
    echo "\n";

    $rows[] = [
        'company' => $name,
        'address' => $address,
        'phone'   => $phone,
        'website' => $website,
        'emails'  => implode(', ', $emails),
    ];

    usleep(DELAY_MS * 1000);
}

// ── Write CSV ──────────────────────────────────────────────────────────────
if ($append && file_exists(OUTPUT)) {
    // Append new rows only (no header)
    $fh = fopen(OUTPUT, 'a');
    foreach ($rows as $row) {
        fputcsv($fh, array_values($row), ',', '"', '\\');
    }
    fclose($fh);
    echo "\nAppended " . count($rows) . " new rows to: " . OUTPUT . "\n";
} else {
    $fh = fopen(OUTPUT, 'w');
    fputcsv($fh, ['company', 'address', 'phone', 'website', 'emails'], ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($fh, array_values($row), ',', '"', '\\');
    }
    fclose($fh);
}

$withEmail = count(array_filter($rows, fn($r) => $r['emails'] !== ''));
echo "Done. {$withEmail}/" . count($rows) . " new leads have emails.\n";
echo "Saved to: " . OUTPUT . "\n";
