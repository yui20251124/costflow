<?php
session_start();

/**
 * meeting.php は「会議詳細」ページ。
 * 社員が登録直後に見る想定なので staff / admin どちらでも閲覧可にする。
 * （不要なら staff のみに絞ってOK）
 */
$role = $_SESSION["role"] ?? "";
if ($role !== "staff" && $role !== "admin") {
  header("Location: /costflow/login.php");
  exit;
}

require_once __DIR__ . "/db.php";
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$meetingId = intval($_GET["id"] ?? 0);
if ($meetingId <= 0) {
  http_response_code(400);
  echo "<h1>エラー</h1><pre>meeting id invalid</pre>";
  exit;
}

// 会議取得
$stmt = $pdo->prepare("
  SELECT id, title, duration_min, annual_work_hours, cost_yen, decision_count, created_at
  FROM meetings
  WHERE id = ?
");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
  http_response_code(404);
  echo "<h1>エラー</h1><pre>meeting not found</pre>";
  exit;
}

// 参加者取得（名前付き）
$stmt = $pdo->prepare("
  SELECT
    mp.employee_id,
    e.name,
    mp.join_minutes,
    mp.cost_yen
  FROM meeting_participants mp
  JOIN employees e ON e.id = mp.employee_id
  WHERE mp.meeting_id = ?
  ORDER BY mp.cost_yen DESC, mp.employee_id ASC
");
$stmt->execute([$meetingId]);
$participants = $stmt->fetchAll();

// 戻り先（roleで分岐）
$backUrl = ($role === "admin")
  ? "/costflow/admin/dashboard.php"
  : "/costflow/app/create_meeting.php";
$backLabel = ($role === "admin")
  ? "← 管理ダッシュボードへ戻る"
  : "← 会議登録に戻る";
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>会議詳細 | CostFlow</title>

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
      gap: 12px;
      margin-bottom: 18px;
    }
    .topbar a{
      text-decoration:none;
      color:#333;
      font-size:14px;
    }
    .topbar a:hover{ text-decoration:underline; }
    h1{ margin: 6px 0 12px; }
    .meta{
      color:#666;
      font-size:13px;
      margin-bottom: 14px;
    }
    .card{
      border:1px solid #ddd;
      border-radius: 10px;
      padding: 14px;
      margin-bottom: 16px;
    }
    .kpis{
      display:grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-top: 10px;
    }
    .kpi{
      border:1px solid #eee;
      border-radius:10px;
      padding:10px;
      background:#fafafa;
    }
    .kpi .label{ color:#666; font-size:12px; }
    .kpi .value{ font-size:18px; font-weight:700; margin-top:4px; }
    table{
      width:100%;
      border-collapse: collapse;
      border:1px solid #ddd;
    }
    th, td{
      border-bottom:1px solid #eee;
      padding: 10px;
      text-align:left;
      vertical-align: top;
      font-size:14px;
    }
    th{
      background:#f7f7f7;
      font-size:12px;
      color:#666;
      text-transform: uppercase;
      letter-spacing: .06em;
      white-space: nowrap;
    }
    .right{ text-align:right; }
    @media (max-width: 820px){
      .kpis{ grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <div class="topbar">
    <!-- ✅ 社員側にはダッシュボード導線を出さない（roleで自然に戻り先分岐） -->
    <a href="<?= h($backUrl) ?>"><?= h($backLabel) ?></a>

    <a href="/costflow/logout.php">ログアウト</a>
  </div>

  <h1><?= h($meeting["title"]) ?></h1>
  <div class="meta">
    会議ID: <?= h($meeting["id"]) ?> /
    作成: <?= h($meeting["created_at"]) ?>
  </div>

  <div class="card">
    <div class="kpis">
      <div class="kpi">
        <div class="label">会議時間</div>
        <div class="value"><?= h($meeting["duration_min"]) ?> 分</div>
      </div>
      <div class="kpi">
        <div class="label">会議コスト</div>
        <div class="value"><?= number_format((int)$meeting["cost_yen"]) ?> 円</div>
      </div>
      <div class="kpi">
        <div class="label">決定数</div>
        <div class="value"><?= h($meeting["decision_count"]) ?></div>
      </div>
    </div>

    <div style="margin-top:10px; color:#666; font-size:13px;">
      年間労働時間：<?= h($meeting["annual_work_hours"]) ?>h（固定）
    </div>
  </div>

  <div class="card">
    <h2 style="margin:0 0 10px;">参加者</h2>

    <?php if (count($participants) === 0): ?>
      <p style="color:#666; margin:0;">参加者がありません。</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>社員</th>
            <th class="right">参加分数</th>
            <th class="right">コスト(円)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($participants as $p): ?>
            <tr>
              <td><?= h($p["name"]) ?></td>
              <td class="right"><?= h($p["join_minutes"]) ?></td>
              <td class="right"><?= number_format((int)$p["cost_yen"]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</body>
</html>
