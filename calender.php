<?php
// ------------------------------------------------------------
// 会議室予約カレンダー（部屋ごとの予約管理対応）
// ------------------------------------------------------------

const HOURS_START = 8;   // 開始時刻
const HOURS_END   = 21;  // 終了時刻
const SLOT_MINUTES = 60; // スロット幅（分）
const STORAGE_FILE = __DIR__ . '/reservations.json';

date_default_timezone_set('Asia/Tokyo');

// ====== ユーティリティ ======
function ensure_storage_file(): void {
    if (!file_exists(STORAGE_FILE)) {
        file_put_contents(STORAGE_FILE, json_encode(["reservations" => []], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

function load_reservations(): array {
    ensure_storage_file();
    $fp = fopen(STORAGE_FILE, 'r');
    if (!$fp) return ["reservations" => []];
    flock($fp, LOCK_SH);
    $json = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($json, true);
    if (!is_array($data)) $data = ["reservations" => []];
    if (!isset($data['reservations']) || !is_array($data['reservations'])) {
        $data['reservations'] = [];
    }
    return $data;
}

function save_reservations(array $data): bool {
    $tmp = STORAGE_FILE . '.tmp';
    $fp = fopen($tmp, 'w');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    $ok = fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if ($ok) {
        return rename($tmp, STORAGE_FILE);
    } else {
        @unlink($tmp);
        return false;
    }
}

function json_response($payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_monday(string $date): string {
    $dt = new DateTime($date);
    $w = (int)$dt->format('N'); // 1=Mon .. 7=Sun
    if ($w !== 1) {
        $dt->modify('-' . ($w - 1) . ' days');
    }
    return $dt->format('Y-m-d');
}

function dt_range_for_week(string $weekStart): array {
    $start = new DateTime($weekStart . ' 00:00:00');
    $end = clone $start; $end->modify('+7 days');
    return [$start, $end];
}

function sanitize_text(?string $s): string { return trim((string)$s); }

// ====== 会議室情報 ======
$room = $_GET['room'] ?? 'large';
$roomName = $room === 'large' ? '大会議室' : '小会議室';

// ====== API ======
if (isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']))) {
    $action = $_GET['action'] ?? $_POST['action'];

    if ($action === 'list') {
        $start = sanitize_text($_GET['start'] ?? $_POST['start'] ?? '');
        $end   = sanitize_text($_GET['end']   ?? $_POST['end']   ?? '');
        $roomParam = sanitize_text($_GET['room'] ?? $_POST['room'] ?? '');
        if (!$start || !$end || !$roomParam) json_response(['error' => 'start/end/room required'], 400);
        $data = load_reservations();
        $items = array_values(array_filter($data['reservations'], function($r) use ($start, $end, $roomParam) {
            return ($r['datetime'] >= $start && $r['datetime'] < $end && $r['room'] === $roomParam);
        }));
        json_response(['reservations' => $items]);
    }

    if ($action === 'reserve') {
        $datetime = sanitize_text($_POST['datetime'] ?? '');
        $name     = sanitize_text($_POST['name'] ?? '');
        $note     = sanitize_text($_POST['note'] ?? '');
        $roomParam = sanitize_text($_POST['room'] ?? '');
        if (!$datetime || !$name || !$roomParam) json_response(['error' => 'datetime/name/room required'], 400);
        $data = load_reservations();
        foreach ($data['reservations'] as $r) {
            if ($r['datetime'] === $datetime && $r['room'] === $roomParam) {
                json_response(['error' => 'already_reserved'], 409);
            }
        }
        $new = [
            'id' => bin2hex(random_bytes(8)),
            'datetime' => $datetime,
            'room' => $roomParam,
            'name' => $name,
            'note' => $note,
            'created_at' => (new DateTime())->format('Y-m-d H:i:s')
        ];
        $data['reservations'][] = $new;
        if (!save_reservations($data)) json_response(['error' => 'save_failed'], 500);
        json_response(['ok' => true, 'reservation' => $new]);
    }

    if ($action === 'cancel') {
        $id = sanitize_text($_POST['id'] ?? '');
        if (!$id) json_response(['error' => 'id required'], 400);
        $data = load_reservations();
        $before = count($data['reservations']);
        $data['reservations'] = array_values(array_filter($data['reservations'], fn($r) => $r['id'] !== $id));
        if ($before === count($data['reservations'])) json_response(['error' => 'not_found'], 404);
        if (!save_reservations($data)) json_response(['error' => 'save_failed'], 500);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'unknown action'], 400);
}

// ====== ビュー ======
$weekStartParam = $_GET['weekStart'] ?? '';
$weekStart = $weekStartParam ? get_monday($weekStartParam) : get_monday(date('Y-m-d'));
[$weekStartDT, $weekEndDT] = dt_range_for_week($weekStart);
$prevWeek = (clone $weekStartDT)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $weekStartDT)->modify('+7 days')->format('Y-m-d');

$days = [];
for ($i=0; $i<7; $i++) {
    $d = (clone $weekStartDT)->modify("+{$i} days");
    $days[] = $d;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($roomName) ?>の予約カレンダー</title>
<style>
    :root {
        --grid-border: #e5e7eb; /* gray-200 */
        --grid-hover: #f3f4f6;  /* gray-100 */
        --reserved-bg: #dbeafe; /* blue-100 */
        --reserved-border: #60a5fa; /* blue-400 */
        --primary: #2563eb;     /* blue-600 */
        --danger: #ef4444;      /* red-500 */
    }
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, "Noto Sans JP", sans-serif; margin: 0; background: #fafafa; color: #111827; }
    .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }

    .header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .nav { display:flex; gap:8px; align-items:center; }
    .btn { padding: 8px 12px; border: 1px solid var(--grid-border); background: white; cursor: pointer; border-radius: 10px; }
    .btn:hover { background: var(--grid-hover); }
    .btn.primary { background: var(--primary); color: white; border-color: var(--primary); }

    .range { font-weight: 600; }

    .legend { display:flex; gap:12px; align-items:center; font-size: 14px; color:#374151; }
    .legend .chip { width:14px; height:14px; border-radius:4px; display:inline-block; margin-right:6px; border:1px solid var(--grid-border); }
    .chip.free { background:white; }
    .chip.reserved { background: var(--reserved-bg); border-color: var(--reserved-border); }

    .calendar {
        margin-top: 16px;
        display: grid;
        grid-template-columns: 80px repeat(7, 1fr);
        border: 1px solid var(--grid-border);
        border-radius: 12px;
        overflow: hidden;
        background: white;
    }

    .col-header { background:#f9fafb; border-bottom:1px solid var(--grid-border); padding:10px; text-align:center; font-weight:600; }
    .time-col { background:#f9fafb; border-right:1px solid var(--grid-border); }
    .time-cell { border-bottom:1px solid var(--grid-border); padding:8px; font-size:12px; color:#4b5563; height:44px; display:flex; align-items:center; justify-content:center; }

    .slot-cell { border-left:1px solid var(--grid-border); border-bottom:1px solid var(--grid-border); height:44px; position:relative; cursor:pointer; }
    .slot-cell:hover { background: var(--grid-hover); }
    .slot-cell.reserved { background: var(--reserved-bg); }

    .slot-content { position:absolute; inset:4px; border:1px dashed transparent; border-radius:8px; padding:6px; font-size:12px; display:flex; align-items:center; justify-content:space-between; gap:6px; }
    .slot-cell.reserved .slot-content { border-color: var(--reserved-border); }
    .slot-name { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    .small { font-size:12px; color:#6b7280; }

    dialog[open] { border:none; border-radius:12px; padding:0; width: min(420px, 90vw); }
    .modal { padding:16px; }
    .modal h3 { margin:0 0 12px; }
    .field { margin-bottom:10px; }
    .field label { display:block; font-size:12px; color:#374151; margin-bottom:6px; }
    .field input, .field textarea { width:100%; padding:8px; border:1px solid var(--grid-border); border-radius:8px; font: inherit; }
    .actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
    .danger { background: var(--danger); color:white; border-color: var(--danger); }

    .footnote { margin-top: 10px; font-size: 12px; color: #6b7280; }
    /* 追加: ○×マーク用 */
    .mark {
        font-size: 16px;
        font-weight: bold;
        display: block;
        text-align: center;
    }
    .mark.ok { color: red; }   /* 予約可能（〇） */
    .mark.ng { color: blue; }  /* 予約不可（×） */
</style>
</head>
<body>
<div class="container">
  <header class="py-4 text-center">
    <h1 class="fw-bold"><?= htmlspecialchars($roomName) ?>の予約カレンダー</h1>
  </header>

  <div class="header">
    <div class="nav">
      <a class="btn" href="?room=<?= $room ?>&weekStart=<?= $prevWeek ?>">◀ 前の週</a>
      <a class="btn" href="?room=<?= $room ?>&weekStart=<?= get_monday(date('Y-m-d')) ?>">今週</a>
      <a class="btn" href="?room=<?= $room ?>&weekStart=<?= $nextWeek ?>">次の週 ▶</a>
    </div>
    <div class="range">
      <?=htmlspecialchars($weekStartDT->format('Y/m/d (D)'))?> 〜 <?=htmlspecialchars((clone $weekEndDT)->modify('-1 day')->format('Y/m/d (D)'))?>
    </div>
  </div>

  <div class="calendar" id="calendar" data-week-start="<?=$weekStartDT->format('Y-m-d')?>" data-week-end="<?=$weekEndDT->format('Y-m-d')?>" data-room="<?=$room?>">
    <div class="col-header"></div>
    <?php foreach ($days as $d): ?>
      <div class="col-header"><?= htmlspecialchars($d->format('n/j (D)')) ?></div>
    <?php endforeach; ?>

    <?php for ($h = HOURS_START; $h < HOURS_END; $h++): ?>
      <div class="time-col"><div class="time-cell"><?=sprintf('%02d:00', $h)?></div></div>
      <?php foreach ($days as $d): ?>
        <?php $slotDT = clone $d; $slotDT->setTime($h,0,0); $iso = $slotDT->format('Y-m-d H:i:s'); ?>
        <div class="slot-cell" data-datetime="<?=$iso?>">
          <div class="slot-content">
            <span class="mark ok">〇</span>
            <span class="slot-name"></span>
            <span class="small"></span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endfor; ?>
  </div>
</div>

<dialog id="reserveDialog">
  <form method="dialog" class="modal" id="reserveForm">
    <h3>予約の作成</h3>
    <div class="field"><label>日時</label><input type="text" id="f_datetime" readonly></div>
    <div class="field"><label>お名前（必須）</label><input type="text" id="f_name" required></div>
    <div class="field"><label>メモ</label><textarea id="f_note" rows="3"></textarea></div>
    <div class="actions">
      <button class="btn" value="cancel">閉じる</button>
      <button class="btn primary" id="saveBtn" value="default">保存</button>
    </div>
  </form>
</dialog>

<dialog id="detailDialog">
  <form method="dialog" class="modal">
    <h3>予約詳細</h3>
    <div class="field"><label>日時</label><input type="text" id="d_datetime" readonly></div>
    <div class="field"><label>お名前</label><input type="text" id="d_name" readonly></div>
    <div class="field"><label>メモ</label><textarea id="d_note" rows="3" readonly></textarea></div>
    <div class="actions">
      <button class="btn" value="cancel">閉じる</button>
      <button class="btn danger" id="deleteBtn" value="default">予約を取消</button>
    </div>
  </form>
</dialog>

<script>
(function(){
  const calendar = document.getElementById('calendar');
  const weekStart = calendar.dataset.weekStart + ' 00:00:00';
  const weekEnd   = calendar.dataset.weekEnd + ' 00:00:00';
  const room = calendar.dataset.room;

  async function api(action, payload={}) {
    const body = new URLSearchParams({action, room, ...payload});
    const res = await fetch(location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body
    });
    if(!res.ok) throw new Error('API error: ' + res.status);
    return res.json();
  }

  async function loadWeek(){
    const data = await api('list', { start: weekStart, end: weekEnd });
    const map = new Map();
    for(const r of data.reservations){ map.set(r.datetime, r); }

    document.querySelectorAll('.slot-cell').forEach(cell=>{
      const dt = cell.dataset.datetime;
      const content = cell.querySelector('.slot-content');
      const nameEl = content.querySelector('.slot-name');
      const markEl = content.querySelector('.mark');

      const r = map.get(dt);
      if(r){
        cell.classList.add('reserved');
        nameEl.textContent = r.name;
        markEl.textContent = '×';
        markEl.className = 'mark ng';
        cell.dataset.reservationId = r.id;
      } else {
        cell.classList.remove('reserved');
        nameEl.textContent = '';
        markEl.textContent = '〇';
        markEl.className = 'mark ok';
        delete cell.dataset.reservationId;
      }
    });
  }

  const reserveDialog = document.getElementById('reserveDialog');
  const detailDialog  = document.getElementById('detailDialog');

  document.getElementById('saveBtn').addEventListener('click', async (e)=>{
    e.preventDefault();
    const datetime = document.getElementById('f_datetime').value;
    const name = document.getElementById('f_name').value.trim();
    const note = document.getElementById('f_note').value.trim();
    if(!name){ alert('お名前は必須です'); return; }
    try{
      await api('reserve', { datetime, name, note });
      reserveDialog.close();
      await loadWeek();
    }catch(err){ alert(err.message); }
  });

  document.getElementById('deleteBtn').addEventListener('click', async (e)=>{
    e.preventDefault();
    const id = detailDialog.dataset.reservationId;
    if(!id) return detailDialog.close();
    if(!confirm('この予約を取り消しますか？')) return;
    try{
      await api('cancel', { id });
      detailDialog.close();
      await loadWeek();
    }catch(err){ alert(err.message); }
  });

  calendar.addEventListener('click', (e)=>{
    const cell = e.target.closest('.slot-cell');
    if(!cell) return;
    const dt = cell.dataset.datetime;

    if(cell.classList.contains('reserved')){
      const id = cell.dataset.reservationId;
      const name = cell.querySelector('.slot-name').textContent;
      document.getElementById('d_datetime').value = dt;
      document.getElementById('d_name').value = name;
      document.getElementById('d_note').value = '';
      detailDialog.dataset.reservationId = id;
      detailDialog.showModal();
    } else {
      document.getElementById('f_datetime').value = dt;
      document.getElementById('f_name').value = '';
      document.getElementById('f_note').value = '';
      reserveDialog.showModal();
    }
  });

  loadWeek().catch(err=>alert(err.message));
})();
</script>
</body>
</html>