<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

$jsonFile = __DIR__ . '/guests.json';

if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, "[]\n", LOCK_EX);
}

/**
 * Load all saved RSVP entries from guests.json
 */
function loadGuests(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
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

    $guests = loadGuests($jsonFile);

    if ($guestName !== '') {
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
        file_put_contents(
            $jsonFile,
            json_encode($guests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );

        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($acceptHeader, 'application/json') !== false) {
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

    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($acceptHeader, 'application/json') !== false) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Guest name is required.']);
        exit;
    }
}

$guests = loadGuests($jsonFile);
$totalResponses = count($guests);
$attendingCount = 0;
$decliningCount = 0;
$restrictionsCount = 0;

foreach ($guests as $g) {
    $status = $g['attendance'] ?? 'joyfully_accepts';
    if ($status === 'regretfully_declines') {
        $decliningCount++;
    } else {
        $attendingCount++;
    }

    $food = trim((string)($g['foodRestrictions'] ?? 'None'));
    if ($food !== '' && strcasecmp($food, 'none') !== 0 && strcasecmp($food, 'n/a') !== 0) {
        $restrictionsCount++;
    }
}

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
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
      <button type="button" id="exportCsvBtn" class="back-link" style="cursor: pointer;">⬇ Export CSV</button>
    </div>

    <header class="header-block">
      <p class="eyebrow">DECEMBER 17, 2026 • CLIFFORD &amp; JESSA</p>
      <h1 class="script-title">Wedding Guest List</h1>
      <p class="subtitle">Live RSVP responses &amp; dietary notes recorded from the wedding invitation</p>
    </header>

    <?php if (isset($_GET['submitted'])): ?>
      <div class="toast-banner" role="status">
        ✓ Thank you! Your RSVP response has been saved to the guest list.
      </div>
    <?php endif; ?>

    <section class="stats-grid" aria-label="RSVP Summary Statistics">
      <div class="stat-card">
        <div class="stat-num"><?= $totalResponses ?></div>
        <div class="stat-label">Total Responses</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= $attendingCount ?></div>
        <div class="stat-label">Joyfully Accepts</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= $decliningCount ?></div>
        <div class="stat-label">Regretfully Declines</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= $restrictionsCount ?></div>
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
        <button type="button" class="filter-btn active" data-filter="all">All (<?= $totalResponses ?>)</button>
        <button type="button" class="filter-btn" data-filter="joyfully_accepts">Attending (<?= $attendingCount ?>)</button>
        <button type="button" class="filter-btn" data-filter="regretfully_declines">Declined (<?= $decliningCount ?>)</button>
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
        <tbody>
          <?php if (empty($guests)): ?>
            <tr>
              <td colspan="6" class="empty-state">No RSVP responses have been submitted yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach (array_reverse($guests) as $idx => $guest): ?>
              <?php
                $att = $guest['attendance'] ?? 'joyfully_accepts';
                $isAccepting = $att !== 'regretfully_declines';
                $pillClass = $isAccepting ? 'status-accepts' : 'status-declines';
                $label = $guest['attendanceLabel'] ?? ($isAccepting ? 'Joyfully Accepts' : 'Regretfully Declines');
                $food = trim((string)($guest['foodRestrictions'] ?? 'None'));
                $msg = trim((string)($guest['message'] ?? ''));
                $submittedAt = (string)($guest['submittedAt'] ?? '');
              ?>
              <tr class="guest-row" data-status="<?= e($att) ?>">
                <td class="col-index"><?= $idx + 1 ?></td>
                <td class="guest-name"><?= e((string)($guest['guestName'] ?? 'Guest')) ?></td>
                <td>
                  <span class="status-pill <?= $pillClass ?>"><?= e($label) ?></span>
                </td>
                <td class="food-cell"><?= e($food !== '' ? $food : 'None') ?></td>
                <td class="message-cell"><?= $msg !== '' ? '“' . e($msg) . '”' : '—' ?></td>
                <td class="date-cell"><?= e($submittedAt) ?></td>
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
      const rows = document.querySelectorAll('.guest-row');
      const exportCsvBtn = document.getElementById('exportCsvBtn');
      let currentFilter = 'all';

      function filterRows() {
        const query = (searchInput?.value || '').toLowerCase().trim();
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

