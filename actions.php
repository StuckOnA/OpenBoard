<?php
// actions.php - the single endpoint for every POST action that changes data:
// logout, vote, comment, delete_post, delete_comment (owner) and
// admin_delete_post, admin_delete_comment (admin only). It shows no page; it redirects.
require 'db.php';

function fail(int $code, string $message)
{
    http_response_code($code);
    exit($message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST requests only.');
}
csrf_check();
$action = post_str('action');

if ($action === 'logout') {
    unset($_SESSION['user']);
    session_regenerate_id(true);
    $_SESSION['flash'] = ['success', 'You have been logged out.'];
    redirect('index.php');
}

$user      = require_login();   // everything below requires a logged-in user (identity comes from the session)
$postId    = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
$commentId = filter_var($_POST['comment_id'] ?? null, FILTER_VALIDATE_INT);

if ($action === 'vote' || $action === 'comment') {
    $stmt = $pdo->prepare('SELECT id FROM posts WHERE id = :id');
    $stmt->execute([':id' => $postId ?: 0]);
    if (!$stmt->fetchColumn()) {
        fail(404, 'Post not found.');
    }
}

if ($action === 'vote') {
    $value = filter_var($_POST['value'] ?? null, FILTER_VALIDATE_INT);
    if ($value === 0) {                       // remove my vote
        $stmt = $pdo->prepare('DELETE FROM votes WHERE user_id = :u AND post_id = :p');
        $stmt->execute([':u' => $user['id'], ':p' => $postId]);
    } elseif ($value === 1 || $value === -1) {  // add or change my vote
        $stmt = $pdo->prepare(
            'INSERT INTO votes (user_id, post_id, vote_value) VALUES (:u, :p, :v)
             ON DUPLICATE KEY UPDATE vote_value = VALUES(vote_value)'
        );
        $stmt->execute([':u' => $user['id'], ':p' => $postId, ':v' => $value]);
    } else {
        fail(400, 'Invalid vote.');
    }
    redirect('post.php?id=' . $postId);
}

if ($action === 'comment') {
    $body = post_str('body');
    if ($body === '' || mb_strlen($body) > 2000) {
        $_SESSION['flash'] = ['danger', 'A comment must be 1-2000 characters.'];
        redirect('post.php?id=' . $postId);
    }
    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (:p, :u, :b)');
    $stmt->execute([':p' => $postId, ':u' => $user['id'], ':b' => $body]);
    $_SESSION['flash'] = ['success', 'Comment added.'];
    redirect('post.php?id=' . $postId . '#comments');
}

if ($action === 'delete_post') {
    // "AND user_id = :u" is the authorization check: you can only delete your own post.
    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id AND user_id = :u');
    $stmt->execute([':id' => $postId ?: 0, ':u' => $user['id']]);
    if ($stmt->rowCount() === 0) {
        fail(403, 'Post not found, or it is not yours.');
    }
    $_SESSION['flash'] = ['success', 'Post deleted.'];
    redirect('index.php');
}

if ($action === 'delete_comment') {
    $stmt = $pdo->prepare('SELECT post_id FROM comments WHERE id = :id AND user_id = :u');
    $stmt->execute([':id' => $commentId ?: 0, ':u' => $user['id']]);
    $commentPost = $stmt->fetchColumn();
    if (!$commentPost) {
        fail(403, 'Comment not found, or it is not yours.');
    }
    $pdo->prepare('DELETE FROM comments WHERE id = :id AND user_id = :u')
        ->execute([':id' => $commentId, ':u' => $user['id']]);
    $_SESSION['flash'] = ['success', 'Comment deleted.'];
    redirect('post.php?id=' . $commentPost . '#comments');
}

// ---- Admin-only actions. require_admin() re-checks the role in the database each time. ----

if ($action === 'admin_delete_post') {
    require_admin();
    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');   // any post; cascades to its comments, votes, tags
    $stmt->execute([':id' => $postId ?: 0]);
    if ($stmt->rowCount() === 0) {
        fail(404, 'Post not found.');
    }
    $_SESSION['flash'] = ['success', 'Post deleted (admin).'];
    redirect('admin.php?view=posts');
}

if ($action === 'admin_delete_comment') {
    require_admin();
    $stmt = $pdo->prepare('DELETE FROM comments WHERE id = :id');
    $stmt->execute([':id' => $commentId ?: 0]);
    if ($stmt->rowCount() === 0) {
        fail(404, 'Comment not found.');
    }
    $_SESSION['flash'] = ['success', 'Comment deleted (admin).'];
    redirect('admin.php?view=comments');
}

fail(400, 'Unknown action.');   // note: there is deliberately no action that deletes or demotes users
