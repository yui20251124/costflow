<?php
session_start();
if (($_SESSION["role"] ?? "") !== "staff") {
  header("Location: /costflow/login.php");
  exit;
}

require_once __DIR__ . "/../db.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

function show_error($msg){
  http_response_code(400);
  echo "<h1>エラー</h1><pre>".h($msg)."</pre>";
  exit;
}

// ===== 入力 =====
$title         = trim($_POST["title"] ?? "");
$durationMin   = intval($_POST["duration_min"] ?? 0);
$decisionCount = intval($_POST["decision_count"] ?? 0);

// ★ 年間労働時間は会社固定
$annualWorkHours = 1920;

$selected = $_POST["employee_id"] ?? [];   // employee_id[]
$joinMap  = $_POST["join_minutes"] ?? [];  // join_minutes[employee_id] => minutes

// ===== バリデーション =====
if ($title === "") show_error("title missing");
if ($durationMin <= 0) show_error("duration_min invalid");
if (!is_array($selected) || count($selected) === 0) show_error("参加者を1人以上選んでください");

// 重複防止（念のため）
$selected = array_values(array_unique($selected));

$pdo = db();

try {
  $participants = [];
  $totalCost = 0;

  foreach ($selected as $eidRaw) {
    $eid = intval($eidRaw);
    if ($eid <= 0) continue;

    // 参加分数（未指定なら会議時間）
    $joinMin = intval($joinMap[$eid] ?? $durationMin);
    if ($joinMin <= 0) $joinMin = $durationMin;

    // 上限（入力ミス対策）：会議時間を超えない
    if ($joinMin > $durationMin) $joinMin = $durationMin;

    // 社員取得
    $stmt = $pdo->prepare("
      SELECT id, name, annual_total_pay_yen
      FROM employees
      WHERE id = ?
    ");
    $stmt->execute([$eid]);
    $emp = $stmt->fetch();
    if (!$emp) show_error("employee not found: id={$eid}");

    // コスト計算
    $hourlyCost  = $emp["annual_total_pay_yen"] / $annualWorkHours;
    $meetingHour = $joinMin / 60.0;
    $cost = (int) round($hourlyCost * $meetingHour);

    $participants[] = [
      "employee_id"  => $eid,
      "join_minutes" => $joinMin,
      "cost_yen"     => $cost
    ];
    $totalCost += $cost;
  }

  if (count($participants) === 0) {
    show_error("有効な参加者がいません");
  }

  // ===== 保存 =====
  $pdo->beginTransaction();

  // meetings
  $stmt = $pdo->prepare("
    INSERT INTO meetings
      (title, duration_min, annual_work_hours, cost_yen, decision_count)
    VALUES (?, ?, ?, ?, ?)
  ");
  $stmt->execute([
    $title,
    $durationMin,
    $annualWorkHours,
    $totalCost,
    $decisionCount
  ]);
  $meetingId = (int)$pdo->lastInsertId();

  // meeting_participants（cost_yen も保存）
  $stmt2 = $pdo->prepare("
    INSERT INTO meeting_participants
      (meeting_id, employee_id, join_minutes, cost_yen)
    VALUES (?, ?, ?, ?)
  ");
  foreach ($participants as $p) {
    $stmt2->execute([
      $meetingId,
      $p["employee_id"],
      $p["join_minutes"],
      $p["cost_yen"]
    ]);
  }

  $pdo->commit();

  // 詳細ページへ（絶対パス）
  header("Location: /costflow/meeting.php?id=" . $meetingId);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo "<h1>保存失敗</h1><pre>".h($e->getMessage())."</pre>";
  exit;
}
