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
      width: 120px;
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
      max-width: 700px;
    }

    .day {
      text-align: center;
    }

    .day button {
      font-size: 1rem;
      padding: 12px;
      width: 100%;
    }

    .schedule {
      margin-top: 20px;
      padding: 10px;
      border-top: 1px solid #ccc;
      background: #f8f9fa;
      width: 100%;
      font-size: 0.95rem;
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
      <div class="schedule" id="scheduleDisplay">📅 日付をクリックすると予定が表示されます。</div>
    </div>
  </div>

  <script>
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

        // 色分けロジック（曜日優先、祝日は平日のみ赤）
        if (dayOfWeek === 0) {
          btn.classList.add("btn-outline-danger"); // 日曜（赤）
        } else if (dayOfWeek === 6) {
          btn.classList.add("btn-outline-primary"); // 土曜（青）
        } else if (isHoliday) {
          btn.classList.add("btn-outline-danger"); // 平日の祝日（赤）
        } else {
          btn.classList.add("btn-outline-secondary"); // 平日（グレー）
        }

        if (isHoliday) {
          btn.title = holidays[dateKey]; // ツールチップに祝日名
        }

        btn.onclick = () => {
          const scheduleText = schedules[dateKey] || "予定は登録されていません。";
          scheduleDisplay.textContent = `📅 ${year}年${month + 1}月${d}日 の予定：${scheduleText}`;
        };

        cell.appendChild(btn);
        calendarGrid.appendChild(cell);
      }

      // 今日の日付を自動選択（初期表示時）
      const today = new Date();
      const todayYear = today.getFullYear();
      const todayMonth = today.getMonth();
      const todayDate = today.getDate();
      const calendarYear = (todayMonth >= 3) ? todayYear : todayYear + 1;
      const calendarMonth = (todayMonth >= 3) ? todayMonth : todayMonth;

      if (month === calendarMonth) {
        const buttons = document.querySelectorAll(".calendar-grid .day button");
        buttons.forEach(btn => {
          if (btn.textContent === String(todayDate)) {
            btn.click();
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

    // 初期表示：今月のカレンダーを表示
    const now = new Date();
    const currentMonth = now.getMonth(); // 0〜11
    const displayMonth = currentMonth;
    renderCalendar(displayMonth);
  </script>
</body>
</html>