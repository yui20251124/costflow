<?php
session_start();
if (($_SESSION["role"] ?? "") !== "admin") {
  header("Location: /costflow/login.php");
  exit;
}

require_once __DIR__ . "/../db.php";
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// 今月の会議コスト合計
$monthTotal = $pdo->query("
  SELECT COALESCE(SUM(cost_yen), 0) AS total
  FROM meetings
  WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
    AND created_at <  DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), INTERVAL 1 MONTH)
")->fetch();
$monthTotalYen = (int)($monthTotal["total"] ?? 0);

// 今月の「決定ゼロ」合計（問い直し対象）
$monthWaste = $pdo->query("
  SELECT COALESCE(SUM(cost_yen), 0) AS total
  FROM meetings
  WHERE decision_count = 0
    AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
    AND created_at <  DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), INTERVAL 1 MONTH)
")->fetch();
$monthWasteYen = (int)($monthWaste["total"] ?? 0);

$monthDecisionYen = max(0, $monthTotalYen - $monthWasteYen);
$wasteRate = $monthTotalYen > 0 ? round($monthWasteYen / $monthTotalYen * 100, 1) : 0.0;

// 問い直し対象（決定0件 × コスト上位）
$candidates = $pdo->query("
  SELECT id, title, duration_min, cost_yen, decision_count, created_at
  FROM meetings
  WHERE decision_count = 0
  ORDER BY cost_yen DESC
  LIMIT 10
")->fetchAll();

// 今月の会議一覧（今月分のみ）
$meetings = $pdo->query("
  SELECT id, title, duration_min, cost_yen, decision_count, created_at
  FROM meetings
  WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
    AND created_at <  DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), INTERVAL 1 MONTH)
  ORDER BY created_at DESC
  LIMIT 100
")->fetchAll();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ダッシュボード | CostFlow</title>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root{
      --bg: #0b0f19;
      --card: #0f1629;
      --text: #e8eefc;
      --muted: rgba(232,238,252,.70);
      --line: rgba(232,238,252,.12);
      --accent: #7aa2ff;
      --danger: #ff6b6b;
      --ok: #55d6a8;
      --shadow: 0 10px 30px rgba(0,0,0,.35);
      --radius: 14px;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      background: radial-gradient(1200px 800px at 20% -10%, rgba(122,162,255,.25), transparent 60%),
                  radial-gradient(900px 600px at 100% 0%, rgba(85,214,168,.18), transparent 55%),
                  var(--bg);
      color: var(--text);
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans JP", "Apple Color Emoji", "Segoe UI Emoji";
      line-height: 1.6;
    }
    a{ color: var(--accent); text-decoration: none; }
    a:hover{ text-decoration: underline; }

    .container{ max-width:1100px; margin:0 auto; padding:28px 18px 60px; }
    .topbar{ display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:18px; }
    .title{ margin:0; font-size:22px; letter-spacing:.02em; }

    .btn{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      color: var(--text);
      box-shadow: none;
    }
    .btn:hover{
      background: rgba(255,255,255,.07);
      text-decoration:none;
    }

    .hero{
      padding:16px; border:1px solid var(--line); border-radius:var(--radius);
      background: rgba(255,255,255,.04); box-shadow: var(--shadow); margin-bottom:14px;
    }
    .hero strong{ font-size:16px; }
    .hero .em{ color: var(--danger); font-weight:700; }
    .subnote{ margin-top:8px; color:var(--muted); font-size:13px; }

    .grid{ display:grid; grid-template-columns:1.1fr .9fr; gap:14px; margin:14px 0 18px; }
    .card{
      border:1px solid var(--line); border-radius:var(--radius);
      background: rgba(255,255,255,.04); box-shadow: var(--shadow);
      padding:14px; min-height:120px;
    }
    .cards{ display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
    .kpi-label{ color:var(--muted); font-size:13px; margin-bottom:6px; }
    .kpi-value{ font-size:26px; font-weight:800; letter-spacing:.01em; }
    .kpi-sub{ color:var(--muted); font-size:13px; margin-top:6px; }
    .danger{ color: var(--danger); }
    .ok{ color: var(--ok); }

    h2{ margin:20px 0 10px; font-size:16px; letter-spacing:.02em; }

    table{
      width:100%; border-collapse:collapse; border:1px solid var(--line);
      border-radius:12px; overflow:hidden; background: rgba(255,255,255,.03);
    }
    thead th{
      text-align:left; font-size:12px; letter-spacing:.06em; text-transform:uppercase;
      color:var(--muted); background: rgba(255,255,255,.04);
      border-bottom:1px solid var(--line); padding:10px; white-space:nowrap;
    }
    tbody td{ padding:10px; border-bottom:1px solid var(--line); vertical-align:top; }
    tbody tr:hover{ background: rgba(255,255,255,.04); }
    .cell-id a{
      display:inline-flex; padding:4px 8px; border-radius:999px;
      border:1px solid var(--line); background: rgba(255,255,255,.04);
      text-decoration:none;
    }
    .pill{
      display:inline-flex; padding:3px 8px; border-radius:999px; border:1px solid var(--line);
      font-size:12px; color:var(--muted); background: rgba(255,255,255,.03); white-space:nowrap;
    }
    .right{ text-align:right; }
    .muted{ color: var(--muted); }

    @media (max-width: 900px){
      .grid{ grid-template-columns: 1fr; }
      .cards{ grid-template-columns: 1fr; }
      .topbar{ align-items:flex-start; flex-direction:column; }
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="topbar">
      <h1 class="title">ダッシュボード</h1>

      <!-- ✅ 右上にログアウトを追加 -->
      <div style="display:flex; gap:8px;">
        <a class="btn" href="/costflow/logout.php">ログアウト</a>
      </div>
    </div>

    <div class="hero">
      <strong>
        今月、会議に <?= number_format($monthTotalYen) ?> 円使っています。<br>
        そのうち <?= number_format($monthWasteYen) ?> 円（<?= h($wasteRate) ?>%）は
        <span class="em">決定を目的としない高コスト会議</span>です。
      </strong>
      <div class="subnote">
        ※ 参加人数・時間・頻度を見直すための可視化です。
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div class="cards">
          <div>
            <div class="kpi-label">今月の会議コスト</div>
            <div class="kpi-value"><?= number_format($monthTotalYen) ?> <span class="muted" style="font-size:14px;">円</span></div>
            <div class="kpi-sub">会議に参加した人件費の合計（1920h前提）</div>
          </div>
          <div>
            <div class="kpi-label">問い直し対象コスト（決定0件）</div>
            <div class="kpi-value danger"><?= number_format($monthWasteYen) ?> <span class="muted" style="font-size:14px;">円</span></div>
            <div class="kpi-sub">まず見直すならここ（人数/時間/頻度）</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="kpi-label">今月の内訳</div>
        <div style="height:220px;">
          <canvas id="costPie"></canvas>
        </div>
        <div class="kpi-sub">
          <span class="pill">決定あり：<?= number_format($monthDecisionYen) ?> 円</span>
          <span class="pill" style="margin-left:6px;">決定0件：<?= number_format($monthWasteYen) ?> 円</span>
        </div>
      </div>
    </div>

    <h2>問い直し対象の会議（決定を目的としない × コスト上位）</h2>
    <?php if (count($candidates) === 0): ?>
      <div class="card"><span class="muted">まだありません。</span></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>会議名</th>
            <th class="right">時間(分)</th>
            <th class="right">会議コスト(円)</th>
            <th class="right">決定数</th><th>作成</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($candidates as $m): ?>
            <tr>
              <td class="cell-id">
                <a href="/costflow/meeting.php?id=<?= h($m["id"]) ?>"><?= h($m["id"]) ?></a>
              </td>
              <td>
                <?= h($m["title"]) ?><br>
                <span class="muted" style="font-size:12px;">決定0件</span>
              </td>
              <td class="right"><?= h($m["duration_min"]) ?></td>
              <td class="right"><strong class="danger"><?= number_format((int)$m["cost_yen"]) ?></strong></td>
              <td class="right"><?= h($m["decision_count"]) ?></td>
              <td class="muted"><?= h($m["created_at"]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2 style="margin-top:22px;">今月の会議</h2>
    <?php if (count($meetings) === 0): ?>
      <div class="card"><span class="muted">まだ会議がありません。</span></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>会議名</th>
            <th class="right">時間(分)</th>
            <th class="right">会議コスト(円)</th>
            <th class="right">決定数</th><th>作成</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($meetings as $m): ?>
            <tr>
              <td class="cell-id">
                <a href="/costflow/meeting.php?id=<?= h($m["id"]) ?>"><?= h($m["id"]) ?></a>
              </td>
              <td><?= h($m["title"]) ?></td>
              <td class="right"><?= h($m["duration_min"]) ?></td>
              <td class="right"><?= number_format((int)$m["cost_yen"]) ?></td>
              <td class="right">
                <?php if ((int)$m["decision_count"] > 0): ?>
                  <span class="pill ok">決定あり</span>
                <?php else: ?>
                  <span class="pill danger">決定0件</span>
                <?php endif; ?>
              </td>
              <td class="muted"><?= h($m["created_at"]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <script>
    const ctx = document.getElementById('costPie');
    const decisionYen = <?= (int)$monthDecisionYen ?>;
    const wasteYen = <?= (int)$monthWasteYen ?>;

    new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ['決定あり', '決定0件'],
        datasets: [{ data: [decisionYen, wasteYen] }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e8eefc' } },
          tooltip: {
            callbacks: {
              label: (item) => `${item.label}: ${(item.raw||0).toLocaleString()} 円`
            }
          }
        }
      }
    });
  </script>
</body>
</html>
