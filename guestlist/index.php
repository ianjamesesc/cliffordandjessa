<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

$jsonFile = __DIR__ . '/guests.json';
$altJsonFile = __DIR__ . '/guest.json';

/**
 * Load all saved RSVP entries from guests.json (or guest.json fallback)
 */
function loadGuests(string $primaryPath, string $altPath): array
{
    foreach ([$primaryPath, $altPath] as $path) {
        if (file_exists($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        }
    }
    return [];
}

/**
 * Write JSON array safely to disk (with fallback if LOCK_EX is rejected on NFS/mounts)
 */
function writeJsonSafe(string $path, string $payload): bool
{
    $bytes = @file_put_contents($path, $payload, LOCK_EX);
    if ($bytes === false) {
        $bytes = @file_put_contents($path, $payload);
    }
    if ($bytes !== false) {
        @chmod($path, 0666);
        return true;
    }
    return false;
}

/**
 * Diagnostic & JSON data endpoint (?check=1)
 */
if (isset($_GET['check'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $dirWritable = is_writable(__DIR__);
    $fileWritable = file_exists($jsonFile) ? is_writable($jsonFile) : $dirWritable;
    $procUser = function_exists('posix_pwuid') && function_exists('posix_geteuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user())
        : get_current_user();

    echo json_encode([
        'ok' => true,
        'phpWorking' => true,
        'dirWritable' => $dirWritable,
        'fileWritable' => $fileWritable,
        'processUser' => $procUser,
        'guests' => loadGuests($jsonFile, $altJsonFile),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Handle POST submission from the RSVP form
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $jsonBody = json_decode($rawBody, true);
            if (is_array($jsonBody)) {
                $input = $jsonBody;
            } else {
                parse_str($rawBody, $parsed);
                if (is_array($parsed) && !empty($parsed)) {
                    $input = $parsed;
                }
            }
        }
    }

    $guestName = trim((string)($input['guestName'] ?? ''));
    $attendance = trim((string)($input['attendance'] ?? 'joyfully_accepts'));
    $foodRestrictions = trim((string)($input['foodRestrictions'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));

    if ($foodRestrictions === '') {
        $foodRestrictions = 'None';
    }

    $attendanceLabel = $attendance === 'regretfully_declines'
        ? 'Regretfully Declines'
        : 'Joyfully Accepts';

    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    $wantsJson = stripos($acceptHeader, 'application/json') !== false
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($guestName === '') {
        if ($wantsJson) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Guest name is required.']);
            exit;
        }
        header('Location: index.html?error=missing_name', true, 303);
        exit;
    }

    $guests = loadGuests($jsonFile, $altJsonFile);

    $newEntry = [
        'id' => uniqid('rsvp_', true),
        'guestName' => $guestName,
        'attendance' => $attendance === 'regretfully_declines' ? 'regretfully_declines' : 'joyfully_accepts',
        'attendanceLabel' => $attendanceLabel,
        'foodRestrictions' => $foodRestrictions,
        'message' => $message,
        'submittedAt' => date('M d, Y • h:i A'),
        'timestamp' => date('c'),
    ];

    $guests[] = $newEntry;
    $encoded = json_encode($guests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    $wrotePrimary = writeJsonSafe($jsonFile, $encoded);
    $wroteAlt = writeJsonSafe($altJsonFile, $encoded);

    if (!$wrotePrimary && !$wroteAlt) {
        $lastErr = error_get_last()['message'] ?? 'Permission denied writing JSON file.';
        if ($wantsJson) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Server could not write to guests.json / guest.json: ' . $lastErr,
                'fixHint' => 'Run on Linux server: sudo chmod -R 777 guestlist && sudo chcon -R -t httpd_sys_rw_content_t guestlist',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: index.html?error=write_failed', true, 303);
        exit;
    }

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'entry' => $newEntry,
            'total' => count($guests),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: index.html?submitted=1', true, 303);
    exit;
}

header('Location: index.html', true, 302);
exit;
