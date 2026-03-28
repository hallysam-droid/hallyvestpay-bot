<?php
// 1. Get the DB_URL from the Environment Variable we set in Step 2
$db_url = getenv("DB_URL");

if (!$db_url) {
    die("Error: DB_URL environment variable is missing.");
}

// 2. Parse the URL to get host, user, password, and database name
$parts = parse_url($db_url);

$host = $parts['host'];
$port = $parts['port'] ?? 5432; // Default Postgres port
$user = $parts['user'];
$pass = $parts['pass'];
$dbname = ltrim($parts['path'], '/');

// 3. Create the connection string (SSL is MANDATORY on Render)
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$pass sslmode=require";

// 4. Connect to PostgreSQL
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Database connection failed: " . pg_last_error());
}

// Keep this file to include in your other scripts!
?>
