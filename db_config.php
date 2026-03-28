<?php
// Check both getenv and $_ENV to be 100% sure
$db_url = getenv("DB_URL") ?: ($_ENV["DB_URL"] ?? null);

if (!$db_url) {
    // This will help us debug if it's still failing
    die("Error: DB_URL environment variable is missing. Please check Render Dashboard > Environment.");
}

$parts = parse_url($db_url);

$host = $parts['host'];
$port = $parts['port'] ?? 5432;
$user = $parts['user'];
$pass = $parts['pass'];
$dbname = ltrim($parts['path'], '/');

$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$pass sslmode=require";

$conn = pg_connect($conn_string);

if (!$conn) {
    die("DB connection failed: " . pg_last_error());
}
?>
