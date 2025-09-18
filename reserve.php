<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room = $_POST['room'] ?? '';
    if ($room === 'large') {
        header('Location: calender.php?room=large');
        exit;
    }
    if ($room === 'small') {
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="py-4 text-center shadow-sm">
    <h1 class="fw-bold">会議室予約</h1>
    <p class="text-muted mb-3">予約したい会議室を選択してください。</p>
    <div class="d-flex justify-content-center gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="me.html">トップへ戻る</a>
        <a class="btn btn-outline-secondary" href="yotei.php">年間予定を見る</a>
    </div>
</header>

<main class="d-flex justify-content-center align-items-center">
  <form method="post" class="text-center w-100" style="max-width: 720px;">
    <button type="submit" name="room" value="large"
      class="btn btn-custom mb-4 d-flex align-items-center justify-content-start">
      <img src="images/large.jpg" alt="大会議室" class="room-img me-3">
      <span class="btn-text">大会議室</span>
    </button>

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
