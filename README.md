# 会議室予約システム

このリポジトリは、高橋建設の会議室予約および予定確認のための簡易Webアプリケーションです。トップページ（`me.html`）から予約メニューや年間予定（`yotei.php`）にアクセスできます。

## 主な画面
- **`me.html`**: トップページ。予約メニューと年間予定への導線があります。
- **`reserve.php`**: 会議室の種類（大会議室 / 小会議室）を選択する画面。
- **`calender.php` / `smallcalender.php`**: それぞれ大会議室・小会議室の予約カレンダー。週単位で空き状況を確認し予約・取消ができます。
- **`yotei.php`**: 年間カレンダー。祝日や社内行事に加えて、登録済みの会議室予約を日付ごとに確認できます。

## データベース設定（phpMyAdmin / MySQL）
1. phpMyAdminなどからMySQLにログインし、利用したいデータベースを作成します（例: `meeting_app`）。
2. `config.php` のホスト名・データベース名・ユーザー名・パスワードを環境に合わせて編集します。
3. アプリケーションを初めて開くと、自動的に `reservations` テーブルが作成されます。手動で作成する場合は下記SQLを実行してください。

```sql
CREATE TABLE IF NOT EXISTS reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room ENUM('large','small') NOT NULL,
  datetime DATETIME NOT NULL,
  name VARCHAR(100) NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY room_datetime_unique (room, datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 補足
- 予約カレンダーで「〇」は予約可能、「×」は予約済みを表します。予約済みスロットをクリックすると内容を確認したり取消できます。
- `yotei.php` ではクリックした日付の会議室予約一覧が表示され、年間の予定とあわせて確認できます。
