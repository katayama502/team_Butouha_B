<?php
// ===== PHP部分：ボタン押下時の遷移処理 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['room'] === 'large') {
        // 大会議室選択時に calendar.php に遷移
        header('Location: calender.php?room=large');
        exit;
    } elseif ($_POST['room'] === 'small') {
        // 小会議室選択時に smallcalendar.php に遷移
        header('Location: smallcalender.php?room=small');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>会議室予約</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- カスタムCSS -->
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ================= タイトル部分 ================= -->
<header class="py-4 text-center">
    <h1 class="fw-bold">会議室予約</h1>
</header>

<!-- ================= ボタン部分 ================= -->
<main class="d-flex justify-content-center align-items-center">
  <form method="post" class="text-center">

    <!-- 大会議室ボタン -->
    <button type="submit" name="room" value="large" 
      class="btn btn-custom mb-4 d-flex align-items-center justify-content-start">
      <img src="images/large.jpg" alt="大会議室" class="room-img me-3">
      <span class="btn-text">大会議室</span>
    </button>

    <!-- 小会議室ボタン -->
    <button type="submit" name="room" value="small" 
      class="btn btn-custom d-flex align-items-center justify-content-start">
      <img src="images/small.jpg" alt="小会議室" class="room-img me-3">
      <span class="btn-text">小会議室</span>
    </button>

  </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>