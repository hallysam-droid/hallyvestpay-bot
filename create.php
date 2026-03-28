<?php
include 'db_config.php';

// SQL to create the deposits table
$sql = "CREATE TABLE IF NOT EXISTS deposits (
    id SERIAL PRIMARY KEY,
    user_id BIGINT,
    amount NUMERIC,
    status TEXT DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

$result = pg_query($conn, $sql);

if ($result) {
    echo "✅ Success: Table 'deposits' is ready!";
} else {
    echo "❌ Error: " . pg_last_error($conn);
}
?>
