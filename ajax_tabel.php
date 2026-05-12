<?php
session_start();
header('Content-Type: application/json');

require 'connect.php';
require 'success_error.php';

// ─── Cache busting ────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'clear_cache') {
    unset($_SESSION['users_cache'], $_SESSION['users_params']);
    echo successResponse('Session cache cleared. Next request will re-fetch from DB.', null, 200);
    exit;
}

// ─── Read & normalise request parameters ─────────────────────────────────────
$search = trim(htmlspecialchars($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, (int) ($_GET['limit'] ?? 10));

$allowed_cols = ['id', 'firstname', 'lastname', 'email'];
$sort_by = in_array(htmlspecialchars($_GET['sort_by'] ?? ''), $allowed_cols) ? $_GET['sort_by'] : 'id';
$order = (strtoupper(htmlspecialchars($_GET['order'] ?? '')) === 'DESC') ? 'DESC' : 'ASC';

// Bundle current params so we can compare them to the previous request
$current_params = [
    'search' => $search,
    'page' => $page,
    'limit' => $limit,
    'sort_by' => $sort_by,
    'order' => $order,
];

// ─── Decide whether to use the cache or hit the DB ────────────────────────────
// Re-query ONLY when:
//   • No cache exists yet, OR
//   • Any param differs from the previous request's params
$params_changed = ($_SESSION['users_params'] ?? null) !== $current_params;

if ($params_changed || empty($_SESSION['users_cache'])) {

    // Build the query — sort column is safe (validated against $allowed_cols above)
    $query = "
        SELECT id, firstname, lastname, email
        FROM users
        WHERE firstname LIKE CONCAT('%', ?, '%')
           OR lastname  LIKE CONCAT('%', ?, '%')
           OR email     LIKE CONCAT('%', ?, '%')
        ORDER BY {$sort_by} {$order}
        LIMIT ? OFFSET ?
    ";

    $offset = ($page - 1) * $limit;

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        errorResponse('Prepare failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    // Bind: three search strings + limit + offset
    mysqli_stmt_bind_param($stmt, 'sssii', $search, $search, $search, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        errorResponse('Query failed: ' . mysqli_error($conn), [], 500);
        exit;
    }

    $_SESSION['users_cache'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $_SESSION['users_params'] = $current_params;   // remember what we queried for

    mysqli_free_result($result);
    mysqli_stmt_close($stmt);

    $from_cache = false;
} else {
    $from_cache = true;
}

// ─── Use the cached page data ─────────────────────────────────────────────────
$page_data = $_SESSION['users_cache'];

// We get the total count with a separate lightweight COUNT query so the
// paginator is always accurate regardless of LIMIT/OFFSET.
// (Only run when params changed — same cache guard as above.)

if ($params_changed || !isset($_SESSION['users_total'])) {
    $count_query = "
        SELECT COUNT(*) AS total
        FROM users
        WHERE firstname LIKE CONCAT('%', ?, '%')
           OR lastname  LIKE CONCAT('%', ?, '%')
           OR email     LIKE CONCAT('%', ?, '%')
    ";

    $stmt_count = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($stmt_count, 'sss', $search, $search, $search);
    mysqli_stmt_execute($stmt_count);
    $count_result = mysqli_stmt_get_result($stmt_count);
    $total = (int) mysqli_fetch_assoc($count_result)['total'];
    mysqli_free_result($count_result);
    mysqli_stmt_close($stmt_count);

    $_SESSION['users_total'] = $total;
} else {
    $total = $_SESSION['users_total'];
}

// ─── Pagination meta ──────────────────────────────────────────────────────────
$total_pages = (int) ceil($total / $limit);
$page = min($page, max(1, $total_pages)); // clamp to valid range

// ─── Return JSON response ─────────────────────────────────────────────────────
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