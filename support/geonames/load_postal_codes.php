<?php

/**
 * GeoNames postal code loader
 *
 * Usage:
 *   php support/geonames/load_postal_codes.php [options]
 *
 * Options:
 *   --file /path/to/XX.txt   Data file to load (default: US.txt in this directory)
 *   --country XX             ISO country code (default: auto-detected from filename)
 *
 * Dev vs prod is determined automatically by config/config.php (DEVELOPMENT_MODE).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.' . PHP_EOL);
}

// ── Parse arguments ──────────────────────────────────────────────────────────

$opts = getopt('', ['env:', 'file:', 'country:']);
$env     = $opts['env'] ?? 'development';
$file    = $opts['file'] ?? __DIR__ . '/US.txt';
$country = strtoupper($opts['country'] ?? pathinfo($file, PATHINFO_FILENAME));

if (!in_array($env, ['development', 'production'], true)) {
    fwrite(STDERR, "Error: --env must be 'development' or 'production'.\n");
    exit(1);
}

if (!file_exists($file)) {
    fwrite(STDERR, "Error: data file not found: $file\n");
    exit(1);
}

if (strlen($country) !== 2) {
    fwrite(STDERR, "Error: --country must be a 2-character ISO code (e.g. US).\n");
    exit(1);
}

// ── Bootstrap config ─────────────────────────────────────────────────────────

// Set DEVELOPMENT_MODE so config.php picks the right DB name
$configFile = __DIR__ . '/../../config/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "Error: config/config.php not found. Copy from config.php.example and fill in values.\n");
    exit(1);
}
require_once $configFile;

// ── Connect ──────────────────────────────────────────────────────────────────

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Connected to " . DB_NAME . " (" . $env . ")\n";

// ── Clear existing rows for this country ─────────────────────────────────────

$deleted = $pdo->prepare('DELETE FROM postal_codes WHERE country_code = ?');
$deleted->execute([$country]);
echo "Cleared " . $deleted->rowCount() . " existing rows for country '$country'.\n";

// ── Load ─────────────────────────────────────────────────────────────────────

$batchSize  = 500;
$totalRows  = 0;
$batch      = [];
$startTime  = microtime(true);

$insertSql = 'INSERT IGNORE INTO postal_codes
    (country_code, postal_code, place_name,
     admin_name1, admin_code1, admin_name2, admin_code2, admin_name3, admin_code3,
     latitude, longitude, accuracy)
    VALUES ';

$rowPlaceholder = '(?,?,?,?,?,?,?,?,?,?,?,?)';

$handle = fopen($file, 'r');
if ($handle === false) {
    fwrite(STDERR, "Error: cannot open file: $file\n");
    exit(1);
}

function flushBatch(PDO $pdo, array $batch, string $insertSql, string $rowPlaceholder): void
{
    $placeholders = implode(',', array_fill(0, count($batch), $rowPlaceholder));
    $values = array_merge(...$batch);
    $pdo->prepare($insertSql . $placeholders)->execute($values);
}

while (($line = fgets($handle)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($line === '') {
        continue;
    }

    $cols = explode("\t", $line);
    if (count($cols) < 12) {
        continue;
    }

    $batch[] = [
        $cols[0],                                          // country_code
        $cols[1],                                          // postal_code
        $cols[2],                                          // place_name
        $cols[3] !== '' ? $cols[3] : null,                 // admin_name1
        $cols[4] !== '' ? $cols[4] : null,                 // admin_code1
        $cols[5] !== '' ? $cols[5] : null,                 // admin_name2
        $cols[6] !== '' ? $cols[6] : null,                 // admin_code2
        $cols[7] !== '' ? $cols[7] : null,                 // admin_name3
        $cols[8] !== '' ? $cols[8] : null,                 // admin_code3
        $cols[9] !== '' ? (float)$cols[9] : null,          // latitude
        $cols[10] !== '' ? (float)$cols[10] : null,        // longitude
        $cols[11] !== '' ? (int)$cols[11] : null,          // accuracy
    ];

    $totalRows++;

    if (count($batch) >= $batchSize) {
        flushBatch($pdo, $batch, $insertSql, $rowPlaceholder);
        $batch = [];
        echo "  Inserted $totalRows rows...\r";
    }
}

fclose($handle);

if (!empty($batch)) {
    flushBatch($pdo, $batch, $insertSql, $rowPlaceholder);
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "\nDone. Inserted $totalRows rows for '$country' in {$elapsed}s.\n";
