<?php
/**
 * 共通カレンダー画面
 */
if (!isset($defaultRoom)) {
    $defaultRoom = 'large';
}

require_once __DIR__ . '/db.php';

define('CALENDAR_HOURS_START', 8);
define('CALENDAR_HOURS_END', 21);
define('CALENDAR_SLOT_MINUTES', 60);

date_default_timezone_set('Asia/Tokyo');

$pdo = db();
db_initialize($pdo);

$allowedRooms = ['large', 'small'];
$roomParam = $_GET['room'] ?? $defaultRoom;
if (!in_array($roomParam, $allowedRooms, true)) {
    $roomParam = $defaultRoom;
}
$roomName = $roomParam === 'large' ? '大会議室' : '小会議室';

if (isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']))) {
    $action = $_GET['action'] ?? $_POST['action'];

    $requestRoom = trim((string)($_POST['room'] ?? $_GET['room'] ?? ''));
    if (!in_array($requestRoom, $allowedRooms, true)) {
        $requestRoom = $roomParam;
    }

    if ($action === 'list') {
        $start = trim((string)($_POST['start'] ?? $_GET['start'] ?? ''));
        $end   = trim((string)($_POST['end']   ?? $_GET['end']   ?? ''));
        if ($start === '' || $end === '') {
            return calendar_json_response(['error' => 'start/end required'], 400);
        }
        $reservations = calendar_fetch_reservations($pdo, $start, $end, $requestRoom);
        return calendar_json_response(['reservations' => $reservations]);
    }

    if ($action === 'reserve') {
        $datetime = trim((string)($_POST['datetime'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($datetime === '' || $name === '') {
            return calendar_json_response(['error' => 'validation_error', 'message' => '日時とお名前は必須です。'], 400);
        }
        try {
            $reservation = calendar_create_reservation($pdo, $datetime, $name, $note, $requestRoom);
            return calendar_json_response(['ok' => true, 'reservation' => $reservation]);
        } catch (RuntimeException $e) {
            return calendar_json_response(['error' => 'already_reserved', 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return calendar_json_response(['error' => 'internal_error', 'message' => '予期せぬエラーが発生しました。'], 500);
        }
    }

    if ($action === 'cancel') {
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id === '') {
            return calendar_json_response(['error' => 'id required'], 400);
        }
        if (!ctype_digit($id)) {
            return calendar_json_response(['error' => 'invalid id'], 400);
        }
        $deleted = calendar_delete_reservation($pdo, (int)$id);
        if (!$deleted) {
            return calendar_json_response(['error' => 'not_found', 'message' => '予約が見つかりませんでした。'], 404);
        }
        return calendar_json_response(['ok' => true]);
    }

    return calendar_json_response(['error' => 'unknown action'], 400);
}

$weekStartParam = $_GET['weekStart'] ?? '';
$weekStart = $weekStartParam ? calendar_get_monday($weekStartParam) : calendar_get_monday(date('Y-m-d'));
[$weekStartDT, $weekEndDT] = calendar_week_range($weekStart);
$prevWeek = (clone $weekStartDT)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $weekStartDT)->modify('+7 days')->format('Y-m-d');

$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $weekStartDT)->modify("+{$i} days");
    $days[] = $d;
}

function calendar_json_response($payload, int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function calendar_get_monday(string $date): string
{
    $dt = new DateTime($date);
    $w = (int)$dt->format('N');
    if ($w !== 1) {
        $dt->modify('-' . ($w - 1) . ' days');
    }
    return $dt->format('Y-m-d');
}

function calendar_week_range(string $weekStart): array
{
    $start = new DateTime($weekStart . ' 00:00:00');
    $end = clone $start;
    $end->modify('+7 days');
    return [$start, $end];
}

function calendar_fetch_reservations(PDO $pdo, string $start, string $end, string $room): array
{
    $stmt = $pdo->prepare('SELECT id, room, datetime, name, COALESCE(note, "") AS note FROM reservations WHERE datetime >= :start AND datetime < :end AND room = :room ORDER BY datetime');
    $stmt->execute([
        ':start' => $start,
        ':end' => $end,
        ':room' => $room,
    ]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (string)$row['id'];
    }
    return $rows;
}

function calendar_create_reservation(PDO $pdo, string $datetime, string $name, string $note, string $room): array
{
    if (!calendar_validate_datetime($datetime)) {
        throw new RuntimeException('日時の形式が正しくありません。');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM reservations WHERE room = :room AND datetime = :datetime LIMIT 1');
        $stmt->execute([
            ':room' => $room,
            ':datetime' => $datetime,
        ]);
        if ($stmt->fetch()) {
            throw new RuntimeException('指定の時間帯は既に予約済みです。');
        }

        $insert = $pdo->prepare('INSERT INTO reservations (room, datetime, name, note) VALUES (:room, :datetime, :name, :note)');
        $insert->execute([
            ':room' => $room,
            ':datetime' => $datetime,
            ':name' => $name,
            ':note' => $note !== '' ? $note : null,
        ]);
        $id = $pdo->lastInsertId();
        $pdo->commit();

        return [
            'id' => (string)$id,
            'room' => $room,
            'datetime' => $datetime,
            'name' => $name,
            'note' => $note,
        ];
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        throw $e;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function calendar_delete_reservation(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

function calendar_validate_datetime(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $dt && $dt->format('Y-m-d H:i:s') === $value;
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
        --grid-border: #e5e7eb;
        --grid-hover: #f3f4f6;
        --reserved-bg: #dbeafe;
        --reserved-border: #60a5fa;
        --primary: #2563eb;
        --danger: #ef4444;
    }
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, "Noto Sans JP", sans-serif; margin: 0; background: #fafafa; color: #111827; }
    .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
    .header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .nav { display:flex; gap:8px; align-items:center; }
    .btn { padding: 8px 12px; border: 1px solid var(--grid-border); background: white; cursor: pointer; border-radius: 10px; text-decoration: none; color: inherit; display:inline-flex; align-items:center; justify-content:center; }
    .btn:hover { background: var(--grid-hover); }
    .btn.primary { background: var(--primary); color: white; border-color: var(--primary); }
    .range { font-weight: 600; }
    .legend { display:flex; gap:12px; align-items:center; font-size: 14px; color:#374151; }
    .legend .chip { width:14px; height:14px; border-radius:4px; display:inline-block; margin-right:6px; border:1px solid var(--grid-border); }
    .chip.free { background:white; }
    .chip.reserved { background: var(--reserved-bg); border-color: var(--reserved-border); }
    .calendar { margin-top: 16px; display: grid; grid-template-columns: 80px repeat(7, 1fr); border: 1px solid var(--grid-border); border-radius: 12px; overflow: hidden; background: white; }
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
    .mark { font-size: 16px; font-weight: bold; display: block; text-align: center; }
    .mark.ok { color: #16a34a; }
    .mark.ng { color: #b91c1c; }
</style>
</head>
<body>
<div class="container">
  <header class="py-4 text-center">
    <h1 class="fw-bold"><?= htmlspecialchars($roomName) ?>の予約カレンダー</h1>
    <p class="footnote"><a class="btn" href="reserve.php">予約メニューへ戻る</a>／<a class="btn" href="yotei.php">予定一覧を見る</a></p>
  </header>

  <div class="header">
    <div class="nav">
      <a class="btn" href="?room=<?= urlencode($roomParam) ?>&weekStart=<?= $prevWeek ?>">◀ 前の週</a>
      <a class="btn" href="?room=<?= urlencode($roomParam) ?>&weekStart=<?= calendar_get_monday(date('Y-m-d')) ?>">今週</a>
      <a class="btn" href="?room=<?= urlencode($roomParam) ?>&weekStart=<?= $nextWeek ?>">次の週 ▶</a>
    </div>
    <div class="range">
      <?= htmlspecialchars($weekStartDT->format('Y/m/d (D)')) ?> 〜 <?= htmlspecialchars((clone $weekEndDT)->modify('-1 day')->format('Y/m/d (D)')) ?>
    </div>
  </div>

  <div class="legend">
    <div><span class="chip free"></span> 予約可能</div>
    <div><span class="chip reserved"></span> 予約済み</div>
  </div>

  <div class="calendar" id="calendar" data-week-start="<?= $weekStartDT->format('Y-m-d') ?>" data-week-end="<?= $weekEndDT->format('Y-m-d') ?>" data-room="<?= htmlspecialchars($roomParam) ?>">
    <div class="col-header"></div>
    <?php foreach ($days as $d): ?>
      <div class="col-header"><?= htmlspecialchars($d->format('n/j (D)')) ?></div>
    <?php endforeach; ?>

    <?php for ($h = CALENDAR_HOURS_START; $h < CALENDAR_HOURS_END; $h++): ?>
      <div class="time-col"><div class="time-cell"><?= sprintf('%02d:00', $h) ?></div></div>
      <?php foreach ($days as $d): ?>
        <?php $slotDT = clone $d; $slotDT->setTime($h, 0, 0); $iso = $slotDT->format('Y-m-d H:i:s'); ?>
        <div class="slot-cell" data-datetime="<?= $iso ?>">
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
    const data = await res.json().catch(() => ({}));
    if(!res.ok){
      const message = data.message || '操作に失敗しました。';
      throw new Error(message);
    }
    return data;
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
      const noteEl = content.querySelector('.small');

      const r = map.get(dt);
      if(r){
        cell.classList.add('reserved');
        nameEl.textContent = r.name;
        noteEl.textContent = r.note || '';
        markEl.textContent = '×';
        markEl.className = 'mark ng';
        cell.dataset.reservationId = r.id;
        cell.dataset.note = r.note || '';
        cell.dataset.name = r.name;
      } else {
        cell.classList.remove('reserved');
        nameEl.textContent = '';
        noteEl.textContent = '';
        markEl.textContent = '〇';
        markEl.className = 'mark ok';
        delete cell.dataset.reservationId;
        delete cell.dataset.note;
        delete cell.dataset.name;
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
      const name = cell.dataset.name || cell.querySelector('.slot-name').textContent;
      const note = cell.dataset.note || '';
      document.getElementById('d_datetime').value = dt;
      document.getElementById('d_name').value = name;
      document.getElementById('d_note').value = note;
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
