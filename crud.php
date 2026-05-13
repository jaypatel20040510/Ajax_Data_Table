<?php
session_start();
header('Content-Type: application/json');

require 'connect.php';
require 'success_error.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: clear session cache so next list-fetch hits DB ──────────────────
function clearUserCache() {
    unset($_SESSION['users_cache'], $_SESSION['users_params']);
}

// ────────────────────────────────────────────────────────────────────────────
// GET single user  →  ?action=read&id=<id>
// ────────────────────────────────────────────────────────────────────────────
if ($action === 'read' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    $stmt = mysqli_prepare($conn, 'SELECT id, firstname, lastname, email FROM users WHERE id = ?');
    if (!$stmt) {
        echo errorResponse('Prepare failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo errorResponse('User not found.', [], 404);
        exit;
    }

    echo successResponse('User fetched.', $row, 200);
    exit;
}

// ────────────────────────────────────────────────────────────────────────────
// CREATE  →  POST action=create  {firstname, lastname, email}
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $firstname = trim(htmlspecialchars($_POST['firstname'] ?? ''));
    $lastname  = trim(htmlspecialchars($_POST['lastname']  ?? ''));
    $email     = trim(htmlspecialchars($_POST['email']     ?? ''));

    if ($firstname === '' || $lastname === '' || $email === '') {
        echo errorResponse('All fields are required.', [], 422);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo errorResponse('Invalid email address.', [], 422);
        exit;
    }

    $stmt = mysqli_prepare($conn, 'INSERT INTO users (firstname, lastname, email) VALUES (?, ?, ?)');
    if (!$stmt) {
        echo errorResponse('Prepare failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    mysqli_stmt_bind_param($stmt, 'sss', $firstname, $lastname, $email);
    $ok = mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo errorResponse('Insert failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    clearUserCache();

    echo successResponse('User created successfully.', [
        'id'        => $newId,
        'firstname' => $firstname,
        'lastname'  => $lastname,
        'email'     => $email,
    ], 201);
    exit;
}

// ────────────────────────────────────────────────────────────────────────────
// UPDATE  →  POST action=update  {id, firstname, lastname, email}
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $id        = (int) ($_POST['id'] ?? 0);
    $firstname = trim(htmlspecialchars($_POST['firstname'] ?? ''));
    $lastname  = trim(htmlspecialchars($_POST['lastname']  ?? ''));
    $email     = trim(htmlspecialchars($_POST['email']     ?? ''));

    if ($id <= 0 || $firstname === '' || $lastname === '' || $email === '') {
        echo errorResponse('All fields are required.', [], 422);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo errorResponse('Invalid email address.', [], 422);
        exit;
    }

    $stmt = mysqli_prepare($conn, 'UPDATE users SET firstname=?, lastname=?, email=? WHERE id=?');
    if (!$stmt) {
        echo errorResponse('Prepare failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    mysqli_stmt_bind_param($stmt, 'sssi', $firstname, $lastname, $email, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo errorResponse('Update failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    clearUserCache();

    echo successResponse('User updated successfully.', [
        'id'        => $id,
        'firstname' => $firstname,
        'lastname'  => $lastname,
        'email'     => $email,
    ], 200);
    exit;
}

// ────────────────────────────────────────────────────────────────────────────
// DELETE  →  POST action=delete  {id}
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo errorResponse('Invalid user ID.', [], 422);
        exit;
    }

    $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
    if (!$stmt) {
        echo errorResponse('Prepare failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo errorResponse('Delete failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    clearUserCache();

    echo successResponse('User deleted successfully.', ['id' => $id], 200);
    exit;
}

// ── Catch-all ────────────────────────────────────────────────────────────────
echo errorResponse('Invalid action.', [], 400);
?>
