<?php
session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// すでにログイン済みならロール別に飛ばす
if (($_SESSION["role"] ?? "") === "admin") {
  header("Location: /costflow/admin/dashboard.php");
  exit;
}
if (($_SESSION["role"] ?? "") === "staff") {
  header("Location: /costflow/app/create_meeting.php");
  exit;
}

$error = "";

// POSTでログイン処理
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $id = trim($_POST["id"] ?? "");
  $pw = trim($_POST["pw"] ?? "");

  // ★MVP用：超簡易（IDでロール分け。PWは固定でOK）
  $users = [
    "admin" => ["pw" => "admin", "role" => "admin", "redirect" => "/costflow/admin/dashboard.php"],
    "staff" => ["pw" => "staff", "role" => "staff", "redirect" => "/costflow/app/create_meeting.php"],
  ];

  if (!isset($users[$id])) {
    $error = "IDが違います（admin / staff）";
  } elseif ($pw !== $users[$id]["pw"]) {
    $error = "パスワードが違います";
  } else {
    $_SESSION["role"] = $users[$id]["role"];
    header("Location: " . $users[$id]["redirect"]);
    exit;
  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ログイン | CostFlow</title>
</head>
<body>
  <h1>ログイン</h1>

  <?php if ($error !== ""): ?>
    <p style="color:red;"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="POST" action="/costflow/login.php">
    <div>
      <label>ID：
        <input name="id" value="<?= h($_POST["id"] ?? "admin") ?>" required>
      </label>
    </div>

    <div style="margin-top:8px;">
      <label>PW：
        <input type="password" name="pw" required>
      </label>
    </div>

    <div style="margin-top:10px;">
      <button type="submit">ログイン</button>
    </div>
  </form>

  <p style="margin-top:14px;color:#666;">
    テスト用：admin/admin または staff/staff
  </p>
</body>
</html>
