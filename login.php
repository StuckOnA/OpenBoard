<?php
// login.php - check username + password, then remember the user in the session.
require 'db.php';
if (current_user()) {
    redirect('index.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_str('username');
    $password = post_raw('password');

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);   // new session ID after login (prevents session fixation)
        $_SESSION['user']  = ['id' => (int)$row['id'], 'username' => $row['username']];
        $_SESSION['flash'] = ['success', 'Logged in as @' . $row['username'] . '.'];
        redirect('index.php');
    }
    $error = 'Invalid username or password.';   // same message either way: don't reveal which was wrong
}

$pageTitle = 'Log in';
require 'header.php';
?>
<h1 class="h3 mb-4">Log in</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="login.php">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label for="username" class="form-label">Username</label>
    <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>">
  </div>
  <div class="mb-3">
    <label for="password" class="form-label">Password</label>
    <input type="password" class="form-control" id="password" name="password">
  </div>
  <button class="btn btn-primary">Log in</button>
  <a class="btn btn-link" href="register.php">Create an account</a>
</form>
<?php require 'footer.php'; ?>
