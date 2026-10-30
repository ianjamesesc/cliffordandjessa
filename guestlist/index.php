<?php
error_reporting(0);
@ini_set('display_errors', '0');
@date_default_timezone_set('Asia/Manila');

$jsonFile = dirname(__FILE__) . '/guests.json';
$altJsonFile = dirname(__FILE__) . '/guest.json';

if (!file_exists($jsonFile)) {
    @file_put_contents($jsonFile, "[]\n");
    @chmod($jsonFile, 0666);
}

function loadGuestsList($primaryPath, $altPath)
{
    $paths = array($primaryPath, $altPath);
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    return $decoded;
                }
            }
        }
    }
    return array();
}

function writeJsonSafeFile($path, $payload)
{
    clearstatcache(true, $path);
    if (file_exists($path) && !is_writable($path)) {
        @chmod($path, 0666);
        clearstatcache(true, $path);
        if (!is_writable($path)) {
            @unlink($path);
        }
    }

    $bytes = @file_put_contents($path, $payload, LOCK_EX);
    if ($bytes === false) {
        $bytes = @file_put_contents($path, $payload);
    }
    if ($bytes === false) {
        $fp = @fopen($path, 'w');
        if ($fp) {
            $bytes = @fwrite($fp, $payload);
            @fclose($fp);
        }
    }
    if ($bytes !== false) {
        @chmod($path, 0666);
        clearstatcache(true, $path);
        return true;
    }
    return false;
}

function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// 1. Diagnostic & JSON Read Endpoint (?check=1)
if (isset($_GET['check'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $dirWritable = is_writable(dirname(__FILE__));
    $fileWritable = file_exists($jsonFile) ? is_writable($jsonFile) : $dirWritable;
    $procUser = function_exists('get_current_user') ? get_current_user() : 'apache';
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $pw = @posix_getpwuid(@posix_geteuid());
        if (is_array($pw) && isset($pw['name'])) {
            $procUser = $pw['name'];
        }
    }

    echo json_encode(array(
        'ok' => true,
        'phpWorking' => true,
        'phpVersion' => PHP_VERSION,
        'dirWritable' => $dirWritable,
        'fileWritable' => $fileWritable,
        'processUser' => $procUser,
        'guests' => loadGuestsList($jsonFile, $altJsonFile)
    ));
    exit;
}

// 2. Handle RSVP Submission (Supports POST, JSON body, or GET ?action=save)
$writeErrorMsg = '';
$justSubmitted = isset($_GET['submitted']);
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
$isSaveRequest = ($method === 'POST') || (isset($_GET['action']) && $_GET['action'] === 'save') || isset($_POST['guestName']);

if ($isSaveRequest) {
    $input = array();
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        $rawBody = @file_get_contents('php://input');
        if ($rawBody !== false && trim($rawBody) !== '') {
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
        if (empty($input) && !empty($_GET)) {
            $input = $_GET;
        }
    }

    $guestName = isset($input['guestName']) ? trim((string)$input['guestName']) : '';
    $attendance = isset($input['attendance']) ? trim((string)$input['attendance']) : 'joyfully_accepts';
    $foodRestrictions = isset($input['foodRestrictions']) ? trim((string)$input['foodRestrictions']) : '';
    $message = isset($input['message']) ? trim((string)$input['message']) : '';

    if ($foodRestrictions === '') {
        $foodRestrictions = 'None';
    }

    $attendanceKey = ($attendance === 'regretfully_declines') ? 'regretfully_declines' : 'joyfully_accepts';
    $attendanceLabel = ($attendanceKey === 'regretfully_declines')
        ? 'Declined'
        : 'Attending';

    $acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    $xhrHeader = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
    $wantsJson = (stripos($acceptHeader, 'application/json') !== false)
        || ($xhrHeader === 'xmlhttprequest')
        || isset($_GET['ajax']);

    if ($guestName !== '') {
        $guests = loadGuestsList($jsonFile, $altJsonFile);

        $newEntry = array(
            'id' => uniqid('rsvp_'),
            'guestName' => $guestName,
            'attendance' => $attendanceKey,
            'attendanceLabel' => $attendanceLabel,
            'foodRestrictions' => $foodRestrictions,
            'message' => $message,
            'submittedAt' => date('M d, Y - h:i A'),
            'timestamp' => date('c')
        );

        $guests[] = $newEntry;

        $jsonFlags = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $jsonFlags = $jsonFlags | JSON_UNESCAPED_UNICODE;
        }
        $encoded = json_encode($guests, $jsonFlags) . "\n";

        $wrotePrimary = writeJsonSafeFile($jsonFile, $encoded);
        $wroteAlt = writeJsonSafeFile($altJsonFile, $encoded);

        if (!$wrotePrimary && !$wroteAlt) {
            $lastErrObj = error_get_last();
            $lastErr = (is_array($lastErrObj) && isset($lastErrObj['message']))
                ? $lastErrObj['message']
                : 'Permission denied writing to ' . $jsonFile;
            $writeErrorMsg = $lastErr;

            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8', true, 500);
                echo json_encode(array(
                    'ok' => false,
                    'error' => 'Server could not write to guests.json: ' . $lastErr,
                    'fixHint' => 'sudo chown -R apache:apache guestlist && sudo chmod -R 775 guestlist && sudo chcon -R -t httpd_sys_rw_content_t guestlist'
                ));
                exit;
            }
        } else {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'ok' => true,
                    'entry' => $newEntry,
                    'total' => count($guests)
                ));
                exit;
            }
            header('Location: ../index.html?rsvp=submitted&guest=' . rawurlencode($guestName) . '#rsvpSectionHeading', true, 303);
            exit;
        }
    } else {
        header('Location: ../index.html#rsvpSectionHeading', true, 303);
        exit;
    }
}

// 3. Load Guests for Server-Side Table Rendering
if (!isset($guests) || !is_array($guests)) {
    $guests = loadGuestsList($jsonFile, $altJsonFile);
}
$totalResponses = count($guests);
$attendingCount = 0;
$decliningCount = 0;
$restrictionsCount = 0;

foreach ($guests as $g) {
    $status = isset($g['attendance']) ? $g['attendance'] : 'joyfully_accepts';
    if ($status === 'regretfully_declines') {
        $decliningCount++;
    } else {
        $attendingCount++;
    }

    $food = isset($g['foodRestrictions']) ? trim((string)$g['foodRestrictions']) : 'None';
    if ($food !== '' && strcasecmp($food, 'none') !== 0 && strcasecmp($food, 'n/a') !== 0) {
        $restrictionsCount++;
    }
}

$dirWritable = is_writable(dirname(__FILE__));
$fileWritable = file_exists($jsonFile) ? is_writable($jsonFile) : $dirWritable;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Guest List &amp; RSVP Responses — Clifford &amp; Jessa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Great+Vibes&family=Montserrat:wght@300;400;500;600&display=swap"
    rel="stylesheet"
  />
  <style>
    :root {
      --bg-parchment: #FCF9F4;
      --bg-cream-peony: #F7F1E8;
      --cream-daffodil: #F2E4CE;
      --amber-banyan: #C98C50;
      --terracotta: #B05334;
      --terracotta-deep: #8C3B20;
      --ink-espresso: #36231B;
      --ink-umber: #5A3E31;
      --ink-muted: #846858;
      --font-script: 'Great Vibes', cursive;
      --font-serif: 'Cormorant Garamond', Georgia, serif;
      --font-sans: 'Montserrat', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100dvh;
      font-family: var(--font-serif);
      color: var(--ink-espresso);
      background:
        radial-gradient(circle at 18% 12%, rgba(242, 228, 206, 0.72), transparent 48%),
        radial-gradient(circle at 85% 82%, rgba(201, 140, 80, 0.22), transparent 52%),
        linear-gradient(165deg, #F8F3EB 0%, #EFE5D8 52%, #E6D8C6 100%);
      padding: clamp(20px, 4vw, 48px) clamp(14px, 3vw, 32px);
    }

    .guestlist-container {
      max-width: 1080px;
      margin: 0 auto;
      background: #FFFDF9;
      border-radius: 28px;
      padding: clamp(24px, 4vw, 44px);
      border: 1px solid rgba(176, 83, 52, 0.24);
      box-shadow: 0 24px 60px rgba(54, 35, 27, 0.14);
    }

    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 18px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: var(--font-sans);
      font-size: 0.7rem;
      font-weight: 500;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--terracotta-deep);
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid rgba(176, 83, 52, 0.3);
      background: rgba(242, 228, 206, 0.35);
      transition: background-color 0.2s ease;
    }

    .back-link:hover {
      background: rgba(242, 228, 206, 0.7);
    }

    .header-block {
      text-align: center;
      margin-bottom: 28px;
    }

    .eyebrow {
      font-family: var(--font-sans);
      font-size: 0.68rem;
      font-weight: 600;
      letter-spacing: 0.24em;
      text-transform: uppercase;
      color: var(--ink-umber);
    }

    .script-title {
      font-family: var(--font-script);
      font-size: clamp(2.6rem, 6vw, 3.6rem);
      font-weight: 400;
      color: var(--terracotta-deep);
      margin: 4px 0 6px;
    }

    .subtitle {
      font-family: var(--font-serif);
      font-size: 1.05rem;
      color: var(--ink-umber);
    }

    .toast-banner {
      margin-bottom: 20px;
      padding: 12px 18px;
      border-radius: 12px;
      background: rgba(176, 83, 52, 0.12);
      border: 1px solid var(--terracotta);
      color: var(--terracotta-deep);
      font-family: var(--font-sans);
      font-size: 0.78rem;
      font-weight: 500;
      text-align: center;
    }

    /* Summary Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: var(--bg-parchment);
      border: 1px solid rgba(201, 140, 80, 0.32);
      border-radius: 16px;
      padding: 16px 14px;
      text-align: center;
    }

    .stat-num {
      font-family: var(--font-serif);
      font-size: clamp(1.9rem, 4vw, 2.4rem);
      font-weight: 600;
      line-height: 1;
      color: var(--terracotta-deep);
    }

    .stat-label {
      font-family: var(--font-sans);
      font-size: 0.6rem;
      font-weight: 600;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--ink-muted);
      margin-top: 6px;
    }

    /* Filter & Actions Toolbar */
    .controls-bar {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
    }

    .search-input {
      flex: 1 1 240px;
      max-width: 340px;
      padding: 9px 14px;
      border-radius: 999px;
      border: 1px solid rgba(176, 83, 52, 0.32);
      background: var(--bg-parchment);
      font-family: var(--font-sans);
      font-size: 0.78rem;
      color: var(--ink-espresso);
    }

    .filter-group {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .filter-btn {
      padding: 7px 14px;
      border-radius: 999px;
      border: 1px solid rgba(176, 83, 52, 0.28);
      background: var(--bg-parchment);
      color: var(--ink-umber);
      font-family: var(--font-sans);
      font-size: 0.64rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      cursor: pointer;
    }

    .filter-btn.active {
      background: var(--terracotta);
      color: #FFFDF9;
      border-color: var(--terracotta);
    }

    /* Table */
    .table-wrapper {
      width: 100%;
      overflow-x: auto;
      border-radius: 16px;
      border: 1px solid rgba(201, 140, 80, 0.34);
      background: #FFFFFF;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    thead {
      background: linear-gradient(90deg, #F7E8D5 0%, #F2DEC6 100%);
      border-bottom: 1.5px solid rgba(176, 83, 52, 0.28);
    }

    th {
      font-family: var(--font-sans);
      font-size: 0.64rem;
      font-weight: 600;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--ink-espresso);
      padding: 14px 16px;
      white-space: nowrap;
    }

    td {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(201, 140, 80, 0.2);
      font-size: 1rem;
      color: var(--ink-espresso);
      vertical-align: top;
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    tbody tr:hover {
      background: rgba(252, 249, 244, 0.85);
    }

    .col-index {
      font-family: var(--font-sans);
      font-size: 0.72rem;
      color: var(--ink-muted);
      width: 48px;
    }

    .guest-name {
      font-weight: 600;
      font-size: 1.08rem;
      color: var(--ink-espresso);
    }

    .status-pill {
      display: inline-block;
      padding: 4px 11px;
      border-radius: 999px;
      font-family: var(--font-sans);
      font-size: 0.62rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .status-accepts {
      background: rgba(46, 125, 75, 0.12);
      color: #215E37;
      border: 1px solid rgba(46, 125, 75, 0.35);
    }

    .status-declines {
      background: rgba(140, 59, 32, 0.12);
      color: var(--terracotta-deep);
      border: 1px solid rgba(140, 59, 32, 0.3);
    }

    .food-cell {
      font-family: var(--font-serif);
      color: var(--ink-umber);
    }

    .message-cell {
      max-width: 320px;
      color: var(--ink-umber);
      font-style: italic;
    }

    .date-cell {
      font-family: var(--font-sans);
      font-size: 0.7rem;
      color: var(--ink-muted);
      white-space: nowrap;
    }

    .empty-state {
      padding: 42px 20px;
      text-align: center;
      color: var(--ink-muted);
      font-size: 1.1rem;
    }

    @media (max-width: 720px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      th, td {
        padding: 11px 12px;
      }
    }
  </style>
</head>
<body>
  <main class="guestlist-container">
    <div class="top-bar">
      <a href="../index.html" class="back-link">← Back to Wedding Invitation</a>
      <button type="button" id="exportCsvBtn" class="back-link" style="cursor: pointer;">Export CSV</button>
    </div>

    <header class="header-block">
      <p class="eyebrow">DECEMBER 17, 2026 • CLIFFORD &amp; JESSA</p>
      <h1 class="script-title">Wedding Guest List</h1>
      <p class="subtitle">Live RSVP responses &amp; dietary notes recorded from the wedding invitation</p>
    </header>

    <?php if ($justSubmitted): ?>
      <div class="toast-banner" role="status">
        ✓ Thank you! Your RSVP response has been saved to the guest list.
      </div>
    <?php endif; ?>

    <?php if ($writeErrorMsg !== ''): ?>
      <div class="toast-banner" role="alert" style="text-align: left; line-height: 1.5;">
        <strong>⚠️ Could not write to JSON file:</strong> <?php echo e($writeErrorMsg); ?><br />
        Run on your Linux server: <code>sudo chown -R apache:apache <?php echo e(dirname(__FILE__)); ?> &amp;&amp; sudo chmod -R 775 <?php echo e(dirname(__FILE__)); ?> &amp;&amp; sudo chcon -R -t httpd_sys_rw_content_t <?php echo e(dirname(__FILE__)); ?></code>
      </div>
    <?php elseif (!$fileWritable || !$dirWritable): ?>
      <div class="toast-banner" role="alert" style="text-align: left; line-height: 1.5;">
        <strong>⚠️ Linux Write Permission / SELinux Notice:</strong>
        <code>httpd</code> does not have write access to <code><?php echo e($jsonFile); ?></code> yet. Run on your Linux server:<br />
        <code>sudo chown -R apache:apache <?php echo e(dirname(__FILE__)); ?> &amp;&amp; sudo chmod -R 775 <?php echo e(dirname(__FILE__)); ?> &amp;&amp; sudo chcon -R -t httpd_sys_rw_content_t <?php echo e(dirname(__FILE__)); ?></code>
      </div>
    <?php endif; ?>

    <section class="stats-grid" aria-label="RSVP Summary Statistics">
      <div class="stat-card">
        <div class="stat-num" id="statTotal"><?php echo (int)$totalResponses; ?></div>
        <div class="stat-label">Total Responses</div>
      </div>
      <div class="stat-card">
        <div class="stat-num" id="statAccepts"><?php echo (int)$attendingCount; ?></div>
        <div class="stat-label">Joyfully Accepts</div>
      </div>
      <div class="stat-card">
        <div class="stat-num" id="statDeclines"><?php echo (int)$decliningCount; ?></div>
        <div class="stat-label">Regretfully Declines</div>
      </div>
      <div class="stat-card">
        <div class="stat-num" id="statRestrictions"><?php echo (int)$restrictionsCount; ?></div>
        <div class="stat-label">Food Restrictions</div>
      </div>
    </section>

    <div class="controls-bar">
      <input
        type="search"
        id="guestSearchInput"
        class="search-input"
        placeholder="Search guest name, food restriction, or note..."
        aria-label="Search guest list"
      />
      <div class="filter-group" role="tablist" aria-label="Filter by attendance">
        <button type="button" class="filter-btn active" data-filter="all" id="filterAllBtn">All (<?php echo (int)$totalResponses; ?>)</button>
        <button type="button" class="filter-btn" data-filter="joyfully_accepts" id="filterAcceptsBtn">Attending (<?php echo (int)$attendingCount; ?>)</button>
        <button type="button" class="filter-btn" data-filter="regretfully_declines" id="filterDeclinesBtn">Declined (<?php echo (int)$decliningCount; ?>)</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table id="guestListTable">
        <thead>
          <tr>
            <th class="col-index">#</th>
            <th>Guest Name(s)</th>
            <th>Response</th>
            <th>Food Restrictions</th>
            <th>Note for the Couple</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody id="guestTableBody">
          <?php if (empty($guests)): ?>
            <tr>
              <td colspan="6" class="empty-state">No RSVP responses have been submitted yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach (array_reverse($guests) as $idx => $guest): ?>
              <?php
                $att = isset($guest['attendance']) ? $guest['attendance'] : 'joyfully_accepts';
                $isAccepting = ($att !== 'regretfully_declines');
                $pillClass = $isAccepting ? 'status-accepts' : 'status-declines';
                $label = isset($guest['attendanceLabel'])
                    ? $guest['attendanceLabel']
                    : ($isAccepting ? 'Joyfully Accepts' : 'Regretfully Declines');
                $food = isset($guest['foodRestrictions']) ? trim((string)$guest['foodRestrictions']) : 'None';
                $msg = isset($guest['message']) ? trim((string)$guest['message']) : '';
                $submittedAt = isset($guest['submittedAt']) ? (string)$guest['submittedAt'] : '';
              ?>
              <tr class="guest-row" data-status="<?php echo e($att); ?>">
                <td class="col-index"><?php echo (int)($idx + 1); ?></td>
                <td class="guest-name"><?php echo e(isset($guest['guestName']) ? $guest['guestName'] : 'Guest'); ?></td>
                <td>
                  <span class="status-pill <?php echo $pillClass; ?>"><?php echo e($label); ?></span>
                </td>
                <td class="food-cell"><?php echo e($food !== '' ? $food : 'None'); ?></td>
                <td class="message-cell"><?php echo $msg !== '' ? '“' . e($msg) . '”' : '—'; ?></td>
                <td class="date-cell"><?php echo e($submittedAt); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script>
    (() => {
      const searchInput = document.getElementById('guestSearchInput');
      const filterBtns = document.querySelectorAll('.filter-btn');
      const tbody = document.getElementById('guestTableBody');
      const exportCsvBtn = document.getElementById('exportCsvBtn');
      let currentFilter = 'all';

      function filterRows() {
        const query = (searchInput?.value || '').toLowerCase().trim();
        const rows = tbody.querySelectorAll('.guest-row');
        rows.forEach((row) => {
          const status = row.getAttribute('data-status') || '';
          const text = row.textContent.toLowerCase();
          const matchesStatus = currentFilter === 'all' || status === currentFilter;
          const matchesSearch = !query || text.includes(query);
          row.hidden = !(matchesStatus && matchesSearch);
        });
      }

      searchInput?.addEventListener('input', filterRows);

      filterBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
          filterBtns.forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');
          currentFilter = btn.getAttribute('data-filter') || 'all';
          filterRows();
        });
      });

      exportCsvBtn?.addEventListener('click', () => {
        const rows = tbody.querySelectorAll('.guest-row');
        const csvRows = [['#', 'Guest Name(s)', 'Response', 'Food Restrictions', 'Note for the Couple', 'Submitted']];
        rows.forEach((row, index) => {
          if (row.hidden) return;
          const cells = Array.from(row.querySelectorAll('td')).map((td) =>
            `"${(td.textContent || '').trim().replace(/"/g, '""')}"`
          );
          if (cells.length === 6) {
            cells[0] = `"${index + 1}"`;
            csvRows.push(cells);
          }
        });
        const csvContent = csvRows.map((r) => r.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'clifford-and-jessa-guestlist.csv';
        a.click();
        URL.revokeObjectURL(url);
      });
    })();
  </script>
</body>
</html>
