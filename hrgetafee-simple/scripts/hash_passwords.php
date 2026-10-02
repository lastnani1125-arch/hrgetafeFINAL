<?php
// hrgetafee-simple/scripts/hash_passwords.php
// Run in XAMPP Shell: C:\xampp\php\php.exe scripts\hash_passwords.php
require_once __DIR__ . '/../config/database.php'; // expects $conn from config

if (!is_object($conn)) {
    echo "Database connection not available. Check config/database.php\n";
    exit(1);
}

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

$backupFile = $backupDir . '/users_backup_' . date('Ymd_His') . '.sql';
echo "Attempting to back up users table to: {$backupFile}\n";
$cmd = sprintf('"%s" -u%s %s users > "%s"', 'C:\\xampp\\mysql\\bin\\mysqldump.exe', DB_USER, DB_NAME, $backupFile);
exec($cmd, $output, $ret);
if ($ret !== 0) {
    echo "Warning: mysqldump exit code {$ret}. Proceeding without dump.\n";
} else {
    echo "Backup (attempt) complete.\n";
}

$sql = "SELECT user_id, username, password FROM users";
$res = $conn->query($sql);
if (!$res) {
    echo "Failed to query users: " . $conn->error . "\n";
    exit(1);
}

$updated = 0;
while ($row = $res->fetch_assoc()) {
    $user_id = (int)$row['user_id'];
    $username = $row['username'];
    $pw = $row['password'];

    $is_hashed = (strpos($pw, '$2y$') === 0) || (strpos($pw, '$2a$') === 0) || (strpos($pw, '$argon') === 0) || (strpos($pw, '$argon2') === 0);

    if ($is_hashed) {
        echo "user_id={$user_id} ({$username}) already hashed -> skip\n";
        continue;
    }
    if ($pw === null || $pw === '') {
        echo "user_id={$user_id} ({$username}) has empty password -> skip\n";
        continue;
    }

    $new_hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
    if ($new_hash === false) {
        echo "Failed to hash password for user {$username}\n";
        continue;
    }

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    if (!$stmt) {
        echo "Prepare failed: " . $conn->error . "\n";
        continue;
    }
    $stmt->bind_param("si", $new_hash, $user_id);
    if ($stmt->execute()) {
        echo "Updated user_id={$user_id} ({$username}) -> hashed\n";
        $updated++;
    } else {
        echo "Failed to update user_id={$user_id} ({$username}): " . $stmt->error . "\n";
    }
    $stmt->close();
}
echo "Done. Total updated: {$updated}\n";
$conn->close();