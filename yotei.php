<?php
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Tokyo');
$reservations = [];
$reservationsError = null;

try {
    $pdo = db();
    db_initialize($pdo);
    $stmt = $pdo->query('SELECT id, room, datetime, name, COALESCE(note, "") AS note FROM reservations ORDER BY datetime');
    $reservations = $stmt->fetchAll();
} catch (Throwable $e) {
    $reservationsError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>年度カレンダー</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: sans-serif;
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    .back-button {
      position: fixed;
      top: 10px;
      right: 10px;
      z-index: 1002;
      padding: 6px 12px;
      font-size: 0.9rem;
    }

    .hamburger {
      display: none;
      position: fixed;
      top: 10px;
      left: 10px;
      font-size: 28px;
      background: none;
      border: none;
      z-index: 1001;
      cursor: pointer;
    }

    .hamburger.hidden {
      display: none !important;
    }

    .overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.3);
      z-index: 999;
    }

    .overlay.active {
      display: block;
    }

    .main {
      display: flex;
      flex: 1;
      width: 100%;
    }

    .sidebar {
      width: 140px;
      background: #f8f9fa;
      padding: 10px;
      box-sizing: border-box;
      transition: transform 0.3s ease;
    }

    .sidebar button {
      display: block;
      width: 100%;
      margin: 4px 0;
      padding: 6px;
      font-size: 0.9rem;
      border: none;
      background-color: #e2e6ea;
      cursor: pointer;
    }

    .calendar {
      flex: 1;
      padding: 10px;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }

    .calendar h2 {
      margin-bottom: 10px;
      font-size: 1.2rem;
    }

    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 4px;
      width: 100%;
      max-width: 720px;
    }

    .day {
      text-align: center;
    }

    .day button {
      font-size: 1rem;
      padding: 12px;
      width: 100%;
      position: relative;
    }

    .day button.has-reservation::after {
      content: '\25CF';
      display: block;
      font-size: 0.7rem;
      color: #e63946;
      margin-top: 4px;
    }

    .schedule {
      margin-top: 20px;
      padding: 10px;
      border-top: 1px solid #ccc;
      background: #f8f9fa;
      width: 100%;
      max-width: 720px;
      font-size: 0.95rem;
    }

    .schedule .title {
      font-weight: 600;
      margin-bottom: 8px;
    }

    .schedule .section-title {
      font-weight: 600;
      margin-top: 12px;
      margin-bottom: 4px;
    }

    .schedule ul {
      margin: 0;
      padding-left: 20px;
    }

    .schedule li {
      margin-bottom: 4px;
    }

    @media (max-width: 768px) {
      .hamburger {
        display: block;
      }

      .main {
        flex-direction: column;
      }

      .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        transform: translateX(-100%);
        z-index: 1000;
        box-shadow: 2px 0 5px rgba(0,0,0,0.2);
        max-width: 70vw;
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .calendar {
        padding-top: 60px;
      }
    }
  </style>
</head>
<body>
  <a href="me.html" class="back-button btn btn-outline-dark">もどる</a>
  <button class="hamburger" id="hamburgerBtn" onclick="toggleMenu()">☰</button>
  <div class="overlay" id="overlay" onclick="closeMenu()"></div>

  <div class="main">
    <div class="sidebar" id="monthButtons"></div>
    <div class="calendar">
      <h2 id="calendarTitle">カレンダー</h2>
      <div class="calendar-grid" id="calendarGrid"></div>
      <div class="schedule" id="scheduleDisplay">📅 日付をクリックすると予約と予定が表示されます。</div>
    </div>
  </div>

  <script>
    const reservationData = <?php echo json_encode($reservations, JSON_UNESCAPED_UNICODE); ?>;
    const reservationsError = <?php echo json_encode($reservationsError, JSON_UNESCAPED_UNICODE); ?>;

    const monthNames = ["4月", "5月", "6月", "7月", "8月", "9月", "10月", "11月", "12月", "1月", "2月", "3月"];
    const monthNumbers = [3, 4, 5, 6, 7, 8, 9, 10, 11, 0, 1, 2];

    const holidays = {
      "2025-01-01": "元日",
      "2025-01-13": "成人の日",
      "2025-02-11": "建国記念の日",
      "2025-03-20": "春分の日",
      "2025-04-29": "昭和の日",
      "2025-05-03": "憲法記念日",
      "2025-05-04": "みどりの日",
      "2025-05-05": "こどもの日",
      "2025-07-21": "海の日",
      "2025-08-11": "山の日",
      "2025-09-15": "敬老の日",
      "2025-09-23": "秋分の日",
      "2025-10-13": "スポーツの日",
      "2025-11-03": "文化の日",
      "2025-11-23": "勤労感謝の日",
      "2025-12-23": "天皇誕生日"
    };

    const schedules = {
      "2025-04-29": "昭和の日：記念式典",
      "2025-05-03": "憲法記念日：講演会",
      "2025-05-05": "こどもの日：イベント開催",
      "2025-07-21": "海の日：海岸清掃ボランティア",
      "2025-09-15": "敬老の日：地域交流会"
    };

    const monthButtons = document.getElementById("monthButtons");
    const calendarTitle = document.getElementById("calendarTitle");
    const calendarGrid = document.getElementById("calendarGrid");
    const hamburgerBtn = document.getElementById("hamburgerBtn");
    const overlay = document.getElementById("overlay");
    const scheduleDisplay = document.getElementById("scheduleDisplay");

    const reservationsByDate = reservationData.reduce((acc, r) => {
      const key = r.datetime.slice(0, 10);
      if (!acc[key]) {
        acc[key] = [];
      }
      acc[key].push(r);
      return acc;
    }, {});

    Object.values(reservationsByDate).forEach(list => {
      list.sort((a, b) => a.datetime.localeCompare(b.datetime));
    });

    const roomLabels = {
      large: "大会議室",
      small: "小会議室"
    };

    if (reservationsError) {
      scheduleDisplay.textContent = "⚠️ 予約情報を読み込めませんでした。管理者にお問い合わせください。";
    }

    monthNames.forEach((name, i) => {
      const btn = document.createElement("button");
      btn.textContent = name;
      btn.className = "btn btn-light";
      btn.onclick = () => {
        renderCalendar(monthNumbers[i]);
        closeMenu();
      };
      monthButtons.appendChild(btn);
    });

    function formatDateLabel(year, month, day) {
      return `${year}年${month}月${day}日`;
    }

    function formatTime(datetime) {
      const dt = new Date(datetime.replace(' ', 'T'));
      return dt.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
    }

    function renderSchedule(dateKey, label) {
      scheduleDisplay.innerHTML = '';

      const title = document.createElement('div');
      title.className = 'title';
      title.textContent = `📅 ${label} の予定`;
      scheduleDisplay.appendChild(title);

      const staticEvent = schedules[dateKey];
      if (staticEvent) {
        const section = document.createElement('div');
        section.className = 'section-title';
        section.textContent = '社内行事';
        scheduleDisplay.appendChild(section);

        const paragraph = document.createElement('p');
        paragraph.textContent = staticEvent;
        scheduleDisplay.appendChild(paragraph);
      }

      if (reservationsError) {
        const warning = document.createElement('p');
        warning.textContent = '予約情報を表示できません。';
        scheduleDisplay.appendChild(warning);
        return;
      }

      const reservations = reservationsByDate[dateKey] || [];
      const sectionTitle = document.createElement('div');
      sectionTitle.className = 'section-title';
      sectionTitle.textContent = '会議室予約';
      scheduleDisplay.appendChild(sectionTitle);

      if (reservations.length === 0) {
        const none = document.createElement('p');
        none.textContent = '予約は登録されていません。';
        scheduleDisplay.appendChild(none);
        return;
      }

      const list = document.createElement('ul');
      reservations.forEach(r => {
        const li = document.createElement('li');
        const room = roomLabels[r.room] || '会議室';
        const note = r.note ? `（${r.note}）` : '';
        li.textContent = `${formatTime(r.datetime)} ${room}：${r.name}${note}`;
        list.appendChild(li);
      });
      scheduleDisplay.appendChild(list);
    }

    function renderCalendar(month) {
      const year = (month >= 3) ? new Date().getFullYear() : new Date().getFullYear() + 1;
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const startDay = firstDay.getDay();
      const totalDays = lastDay.getDate();

      calendarTitle.textContent = `${year}年 ${monthNames[monthNumbers.indexOf(month)]}`;
      calendarGrid.innerHTML = "";

      ["日", "月", "火", "水", "木", "金", "土"].forEach(day => {
        const header = document.createElement("div");
        header.className = "day fw-bold";
        header.textContent = day;
        calendarGrid.appendChild(header);
      });

      for (let i = 0; i < startDay; i++) {
        calendarGrid.appendChild(document.createElement("div"));
      }

      for (let d = 1; d <= totalDays; d++) {
        const cellDate = new Date(year, month, d);
        const dayOfWeek = cellDate.getDay();
        const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const isHoliday = holidays.hasOwnProperty(dateKey);

        const cell = document.createElement("div");
        cell.className = "day";

        const btn = document.createElement("button");
        btn.textContent = d;
        btn.className = "btn w-100";

        if (dayOfWeek === 0) {
          btn.classList.add("btn-outline-danger");
        } else if (dayOfWeek === 6) {
          btn.classList.add("btn-outline-primary");
        } else if (isHoliday) {
          btn.classList.add("btn-outline-danger");
        } else {
          btn.classList.add("btn-outline-secondary");
        }

        if ((reservationsByDate[dateKey] || []).length > 0) {
          btn.classList.add('has-reservation');
        }

        if (isHoliday) {
          btn.title = holidays[dateKey];
        }

        btn.onclick = () => {
          const label = formatDateLabel(year, month + 1, d);
          renderSchedule(dateKey, label);
        };

        cell.appendChild(btn);
        calendarGrid.appendChild(cell);
      }

      const today = new Date();
      const todayYear = today.getFullYear();
      const todayMonth = today.getMonth();
      const todayDate = today.getDate();
      const calendarYear = (todayMonth >= 3) ? todayYear : todayYear + 1;

      if (month === todayMonth && year === calendarYear) {
        const buttons = document.querySelectorAll(".calendar-grid .day button");
        buttons.forEach(button => {
          if (Number(button.textContent) === todayDate) {
            button.click();
          }
        });
      }
    }

    function toggleMenu() {
      monthButtons.classList.add("open");
      overlay.classList.add("active");
      hamburgerBtn.classList.add("hidden");
    }

    function closeMenu() {
      monthButtons.classList.remove("open");
      overlay.classList.remove("active");
      hamburgerBtn.classList.remove("hidden");
    }

    const now = new Date();
    const currentMonth = now.getMonth();
    renderCalendar(currentMonth);
  </script>
</body>
</html>
