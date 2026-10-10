<?php
// register.php - create an account. A blank username gets a generated one (user12345).
// Every account created here is a normal user: there is no role field in the form,
// and the INSERT below hard-codes 'user'. Admins are made only with create_admin.php.
require 'db.php';
if (current_user()) {
    redirect('index.php');
}

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_str('username');
    $password = post_raw('password');
    $confirm  = post_raw('confirm');

    if ($username !== '' && !preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username must be 3-20 characters: letters, numbers or underscore.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be 8-72 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $hash      = password_hash($password, PASSWORD_DEFAULT);   // never store the plain password
        $generated = ($username === '');
        $stmt      = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:u, :h, 'user')");

        for ($try = 0; $try < 5; $try++) {
            if ($generated) {
                $username = 'user' . random_int(10000, 99999);
            }
            try {
                $stmt->execute([':u' => $username, ':h' => $hash]);
                session_regenerate_id(true);
                $_SESSION['user']  = ['id' => (int)$pdo->lastInsertId(), 'username' => $username];
                $_SESSION['flash'] = ['success', 'Welcome to OpenBoard, @' . $username . '!'];
                redirect('index.php');
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {   // 23000 = unique constraint violated (name taken)
                    throw $e;
                }
                if (!$generated) {
                    $errors[] = 'That username is already taken.';
                    break;
                }
            }
        }
        if (!$errors) {
            $errors[] = 'Could not generate a username. Please try again.';
        }
    }
}

$pageTitle = 'Register';
require 'header.php';
?>
<h1 class="h3 mb-4">Create an account</h1>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>
<form method="post" action="register.php">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label for="username" class="form-label">Username</label>
    <input type="text" class="form-control" id="username" name="username" maxlength="20"
           value="<?= e($username) ?>" placeholder="Leave blank for a random one">
  </div>
  <div class="mb-3">
    <label for="password" class="form-label">Password (min 8 characters)</label>
    <input type="password" class="form-control" id="password" name="password">
  </div>
  <div class="mb-3">
    <label for="confirm" class="form-label">Confirm password</label>
    <input type="password" class="form-control" id="confirm" name="confirm">
  </div>
  <button class="btn btn-primary">Register</button>
  <a class="btn btn-link" href="login.php">I already have an account</a>
</form>
<?php require 'footer.php'; ?>
