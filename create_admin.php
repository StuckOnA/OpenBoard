<?php
// create_admin.php - COMMAND-LINE ONLY. Creates an admin account, or promotes an existing user.
//   php create_admin.php <username>   create the account (asks for a password) or promote it
//   php create_admin.php --hash       only print a password hash (for phpMyAdmin when there is no terminal)
// Safe to run again: it never changes an existing password and never demotes anyone.

if (PHP_SAPI !== 'cli') {   // if someone opens this file in a browser, do nothing
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

function ask(string $prompt): string
{
    echo $prompt;
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
}

function ask_new_password(): string
{
    echo "(The password is visible while you type. Clear the terminal afterwards.)\n";
    $password = ask('Password (8-72 characters): ');
    if (strlen($password) < 8 || strlen($password) > 72) {
        exit("Password must be 8-72 characters.\n");
    }
    if ($password !== ask('Repeat password: ')) {
        exit("Passwords do not match.\n");
    }
    return $password;
}

$arg = $argv[1] ?? '';

if ($arg === '--hash') {
    echo password_hash(ask_new_password(), PASSWORD_DEFAULT) . "\n";
    exit(0);
}

if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $arg)) {
    exit("Usage: php create_admin.php <username>   (3-20 letters, numbers or underscore)\n"
       . "       php create_admin.php --hash\n");
}

require __DIR__ . '/db.php';   // uses config.php, so it works the same locally and on a host with a terminal

$stmt = $pdo->prepare('SELECT id, role FROM users WHERE username = :u');
$stmt->execute([':u' => $arg]);
$row = $stmt->fetch();

if ($row && $row['role'] === 'admin') {
    exit("'$arg' is already an admin. Nothing changed.\n");
}
if ($row) {
    $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id")->execute([':id' => $row['id']]);
    exit("Existing account '$arg' promoted to admin. Its password was not changed.\n");
}

$hash = password_hash(ask_new_password(), PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:u, :h, 'admin')")
    ->execute([':u' => $arg, ':h' => $hash]);
exit("Admin account '$arg' created.\n");
