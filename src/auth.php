<?php
require_once __DIR__ . '/db.php';

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_user(): ?array
{
    ensure_session();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): void
{
    ensure_session();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function login(string $email, string $password): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        ensure_session();
        $_SESSION['user_id'] = $user['id'];
        return true;
    }
    return false;
}

function logout(): void
{
    ensure_session();
    $_SESSION = [];
    session_destroy();
}

function user_can_access_project(array $project, array $user): bool
{
    return $project['is_public'] == 1 || $project['owner_user_id'] == $user['id'];
}
