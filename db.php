<?php
function db(): PDO {
  $host = "127.0.0.1";
  $db   = "costflow";
  $user = "root";
  $pass = ""; // XAMPPデフォルトは空が多い
  $charset = "utf8mb4";

  $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}
