<?php
session_start();
if (($_SESSION["role"] ?? "") !== "staff") {
  header("Location: /costflow/login.php");
  exit;
}

require_once __DIR__ . "/../db.php";

$pdo = db();

// 社員一覧
$employees = $pdo->query("
  SELECT id, name, annual_total_pay_yen
  FROM employees
  ORDER BY id
")->fetchAll();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>会議登録 | CostFlow</title>

  <style>
    body{
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans JP", sans-serif;
      margin: 24px;
      line-height: 1.6;
    }
    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:20px;
    }
    a.logout{
      font-size:14px;
      color:#666;
      text-decoration:none;
    }
    a.logout:hover{
      text-decoration:underline;
    }
    h1{ margin:0; }
  </style>
</head>
<body>

  <!-- ✅ 上部バー -->
  <div class="topbar">
    <h1>会議登録</h1>
    <a class="logout" href="/costflow/logout.php">ログアウト</a>
  </div>

  <form method="POST" action="/costflow/app/save_meeting.php">
    <div>
      <label>会議名：
        <input name="title" value="全体定例" required />
      </label>
    </div>

    <!-- 年間労働時間（1920h）は会社固定 -->

    <div style="margin-top:10px;">
      <label>会議時間（分）：
        <input name="duration_min" type="number" value="60" min="1" required />
      </label>
    </div>

    <div style="margin-top:10px;">
      <label>決定数：
        <input name="decision_count" type="number" value="0" min="0" required />
      </label>
    </div>

    <h2 style="margin-top:18px;">参加者（参加分数）</h2>
    <p>
      ※ 参加分数は会議時間と同じでOK。途中参加なら分数を減らす。<br>
      ※ 人件費は <strong>年間労働時間1920h</strong> を前提に算出します。
    </p>

    <?php foreach ($employees as $e): ?>
      <div style="margin:6px 0;">
        <label>
          <input type="checkbox" name="employee_id[]" value="<?= h($e['id']) ?>">
          <?= h($e['name']) ?>（年総支給 <?= number_format((int)$e['annual_total_pay_yen']) ?> 円）
        </label>
        <label style="margin-left:10px;">
          分：
          <input
            type="number"
            name="join_minutes[<?= h($e['id']) ?>]"
            value="60"
            min="1"
          />
        </label>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:16px;">
      <button type="submit">保存</button>
    </div>
  </form>

</body>
</html>
