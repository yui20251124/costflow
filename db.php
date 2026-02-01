<?php
// db.php（GitHub公開用）
// 本番環境では .env やサーバー側設定を使用する想定

function db(): PDO {
  throw new Exception('Database configuration is not included in this repository.');
}
