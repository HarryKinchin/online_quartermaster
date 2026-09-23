<?php

$env_values = [];
$env_file = __DIR__ . '/.env';
if (is_readable($env_file)) {
  $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (is_array($env_lines)) {
    foreach ($env_lines as $env_line) {
      $env_line = trim($env_line);
      if ($env_line === '' || $env_line[0] === '#' || strpos($env_line, '=') === false) {
        continue;
      }

      list($env_key, $env_value) = explode('=', $env_line, 2);
      $env_key = trim($env_key);
      $env_value = trim($env_value);
      if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $env_key) !== 1) {
        continue;
      }
      if (strlen($env_value) >= 2 && (($env_value[0] === '"' && substr($env_value, -1) === '"') || ($env_value[0] === "'" && substr($env_value, -1) === "'"))) {
        $env_value = substr($env_value, 1, -1);
      }
      $env_values[$env_key] = $env_value;
    }
  }
}

// .env file values take precedence over any stale real environment variables
foreach ($env_values as $env_key => $env_value) {
  putenv("$env_key=$env_value");
}

$servername = getenv('QM_DB_HOST');
$servername = $servername !== false && $servername !== '' ? $servername : (isset($env_values['QM_DB_HOST']) ? $env_values['QM_DB_HOST'] : 'localhost');
$username = getenv('QM_DB_USER');
$username = $username !== false && $username !== '' ? $username : (isset($env_values['QM_DB_USER']) ? $env_values['QM_DB_USER'] : '');
$password = getenv('QM_DB_PASSWORD');
$password = $password !== false && $password !== '' ? $password : (isset($env_values['QM_DB_PASSWORD']) ? $env_values['QM_DB_PASSWORD'] : '');
$dbname = getenv('QM_DB_NAME');
$dbname = $dbname !== false && $dbname !== '' ? $dbname : (isset($env_values['QM_DB_NAME']) ? $env_values['QM_DB_NAME'] : '');

if ($username === false || $password === false || $dbname === false || $username === '' || $password === '' || $dbname === '') {
    error_log('Database configuration is incomplete. Set QM_DB_USER, QM_DB_PASSWORD, and QM_DB_NAME.');
    http_response_code(500);
    exit('The database is not configured.');
}

$conn = new mysqli(hostname: $servername, username: $username, password: $password, database: $dbname);
if ($conn->connect_error) {
  error_log('Database connection failed: ' . $conn->connect_error);
  http_response_code(500);
  exit('The database connection failed.');
}
$conn->set_charset('utf8mb4');
?>