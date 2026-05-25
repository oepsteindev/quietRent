<?php

/**
 * Tampa Bay Tradespeople Lead Harvester
 *
 * Targets small/solo contractors, plumbers, electricians, HVAC technicians,
 * roofers, handymen, and other tradespeople in the Tampa Bay area.
 *
 * Usage:
 *   php tools/harvest_tradespeople.php            # fresh run
 *   php tools/harvest_tradespeople.php --append   # add new results without overwriting
 *
 * Requirements:
 *   - GOOGLE_PLACES_API_KEY in .env
 *
 * Output:
 *   tools/tradespeople_leads.csv
 */

chdir(dirname(__DIR__));

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
define('OUTPUT',  __DIR__ . '/tradespeople_leads.csv');
define('DELAY_MS', 1000);
define('FETCH_TIMEOUT', 8);

function places_search(string $query, ?string $pageToken = null): array
{
    $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query([
        'query' => $query,
        'key'   => API_KEY,
        ...($pageToken ? ['pagetoken' => $pageToken] : []),
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
    $raw  = curl_get($url);
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

function extract_emails(string $html): array
{
    $emails = [];
    preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $html, $m);
    foreach ($m[1] as $e) $emails[] = strtolower($e);
    preg_match_all('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/', $html, $m);
    foreach ($m[1] as $e) {
        $e = strtolower($e);
        if (!preg_match('/\.(png|jpg|jpeg|gif|svg|webp|css|js)$/i', $e)) $emails[] = $e;
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
        $emails = array_merge($emails, extract_emails($html));
        usleep(300_000);
    }
    return array_unique($emails);
}

$append = in_array('--append', $argv ?? []);

$queries = [
    'plumber Tampa FL',
    'plumber St. Petersburg FL',
    'plumber Clearwater FL',
    'plumber Bradenton FL',
    'electrician Tampa FL',
    'electrician St. Petersburg FL',
    'electrician Clearwater FL',
    'HVAC Tampa FL',
    'air conditioning repair Tampa FL',
    'roofer Tampa FL',
    'roofing contractor Tampa FL',
    'handyman Tampa FL',
    'handyman St. Petersburg FL',
    'general contractor Tampa FL',
    'painting contractor Tampa FL',
    'pest control Tampa FL',
    'landscaping Tampa FL',
    'pool service Tampa FL',
    'tile contractor Tampa FL',
    'drywall contractor Tampa FL',
];

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
echo "Searching Google Places for tradespeople…\n";

foreach ($queries as $query) {
    $pageToken = null;
    $pages = 0;
    do {
        if ($pageToken) sleep(2);
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

echo "\nFetching details + scraping websites…\n";

$rows = [];
$done = 0;

foreach ($places as $p) {
    $done++;
    $detail  = place_details($p['place_id']);
    $name    = $detail['name']                   ?? $p['name'] ?? '';
    $address = $detail['formatted_address']      ?? $p['formatted_address'] ?? '';
    $phone   = $detail['formatted_phone_number'] ?? '';
    $website = $detail['website']                ?? '';

    echo "  [{$done}/" . count($places) . "] {$name}";

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

if ($append && file_exists(OUTPUT)) {
    $fh = fopen(OUTPUT, 'a');
    foreach ($rows as $row) fputcsv($fh, array_values($row), ',', '"', '\\');
    fclose($fh);
    echo "\nAppended " . count($rows) . " new rows to: " . OUTPUT . "\n";
} else {
    $fh = fopen(OUTPUT, 'w');
    fputcsv($fh, ['company', 'address', 'phone', 'website', 'emails'], ',', '"', '\\');
    foreach ($rows as $row) fputcsv($fh, array_values($row), ',', '"', '\\');
    fclose($fh);
}

$withEmail = count(array_filter($rows, fn($r) => $r['emails'] !== ''));
echo "Done. {$withEmail}/" . count($rows) . " leads have emails.\n";
echo "Saved to: " . OUTPUT . "\n";
