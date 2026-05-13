<?php
session_start();
header('Content-Type: application/json');

require 'connect.php';
require 'success_error.php';

if (isset($_GET['action']) && $_GET['action'] === 'clear_cache') {
    unset($_SESSION['users_cache'], $_SESSION['users_params']);
    echo successResponse('Session cache cleared. Next request will re-fetch from DB.', null, 200);
    exit;
}

$search = trim(htmlspecialchars($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, (int) ($_GET['limit'] ?? 10));

$allowed_cols = ['id', 'firstname', 'lastname', 'email'];
$sort_by = in_array(htmlspecialchars($_GET['sort_by'] ?? ''), $allowed_cols) ? $_GET['sort_by'] : 'id';
$order = (strtoupper(htmlspecialchars($_GET['order'] ?? '')) === 'DESC') ? 'DESC' : 'ASC';

$query = "SELECT id, firstname, lastname, email, COUNT(*) OVER() AS total
          FROM users
          WHERE firstname LIKE CONCAT('%',?,'%')
             OR lastname  LIKE CONCAT('%',?,'%')
             OR email     LIKE CONCAT('%',?,'%')
          ORDER BY {$sort_by} {$order}
          LIMIT ? OFFSET ?";

$current_params = [
    'search' => $search,
    'page' => $page,
    'limit' => $limit,
    'sort_by' => $sort_by,
    'order' => $order,
];

$params_changed = ($_SESSION['users_params'] ?? null) !== $current_params;

if ($params_changed || empty($_SESSION['users_cache'])) {

    $offset = ($page - 1) * $limit;

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        errorResponse('Prepare failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    mysqli_stmt_bind_param($stmt, 'sssii', $search, $search, $search, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);



    if (!$result) {
        errorResponse('Query failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $total_from_db = !empty($rows) ? (int) $rows[0]['total'] : 0;

    // $row will get 2d Array from mysqli_fetch_all, due to MYSQLI_ASSOC result will be like[[
    //     "columnName"=>"value",
    //     "id"=>"1",
    //     "firstname"=>"John",
    //     "lastname"=>"Doe",
    //     "email"=>"[EMAIL_ADDRESS]",
    //     "total"=>"100"
    // ],[
    //     "id"=>"2",
    //     "firstname"=>"Jane",
    //     "lastname"=>"Doe",
    //     "email"=>"[EMAIL_ADDRESS]",
    //     "total"=>"100"
    // ]]
    $rows = array_map(function ($row) {
        unset($row['total']);
        // unset($row['firstname']);
        return $row;
    }, $rows);

    $_SESSION['users_cache'] = $rows;
    $_SESSION['users_total'] = $total_from_db;
    $_SESSION['users_params'] = $current_params;

    mysqli_free_result($result);
    mysqli_stmt_close($stmt);

    $from_cache = false;
} else {
    $from_cache = true;
}

$page_data = $_SESSION['users_cache'];
$total = $_SESSION['users_total'] ?? 0;

$total_pages = (int) ceil($total / $limit);
$page = min($page, max(1, $total_pages));

echo successResponse("Users fetched", $page_data, 200, [
    'total' => $total,
    'total_pages' => $total_pages,
    'page' => $page,
    'limit' => $limit,
    'sort_by' => $sort_by,
    'order' => $order,
    'search' => $search,
    'from_cache' => $from_cache,
]);
?>