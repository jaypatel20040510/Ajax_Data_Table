<?php
session_start();
header('Content-Type: application/json');

require 'connect.php';
require 'success_error.php';

// ─── Cache busting ────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'clear_cache') {
    unset($_SESSION['users_cache']);
    echo json_encode(['success' => true, 'message' => 'Session cache cleared. Next request will re-fetch from DB.']);
    exit;
}

// ─── Load all data (ONE DB query, then cache in session) ──────────────────────
if (empty($_SESSION['users_cache'])) {
    $query = "
    SELECT 
        id, firstname, lastname, email,
        COUNT(*) OVER() AS total_count
    FROM users
    WHERE firstname LIKE CONCAT('%', ?, '%')
       OR lastname  LIKE CONCAT('%', ?, '%')
       OR email     LIKE CONCAT('%', ?, '%')
    ORDER BY $sort_by $order
    LIMIT ? OFFSET ?
";
    // This is the ONLY DB query in the entire application.
    // SQL_CALC_FOUND_ROWS is not needed here because we store everything in session
    // and do count/filter/sort/paginate in PHP — truly one query only.
    // $result = mysqli_query($conn, "SELECT id, firstname, lastname, email FROM users");
    $result = mysqli_query($conn, $query);


    if (!$result) {
        echo json_encode(['error' => 'Query failed: ' . mysqli_error($conn)]);
        exit;
    }

    $_SESSION['users_cache'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// Retrieve the cached full dataset
$all_users = $_SESSION['users_cache'];

// ─── Read request parameters ──────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, (int) ($_GET['limit'] ?? 10));

$allowed_cols = ['id', 'firstname', 'lastname', 'email'];
$sort_by = in_array($_GET['sort_by'] ?? '', $allowed_cols) ? $_GET['sort_by'] : 'id';
$order = (strtoupper($_GET['order'] ?? '') === 'DESC') ? 'DESC' : 'ASC';

// ─── Step 1: Filter (search) ──────────────────────────────────────────────────
if ($search !== '') {
    $search_lower = strtolower($search);
    $all_users = array_values(array_filter($all_users, function ($row) use ($search_lower) {
        return str_contains(strtolower($row['firstname']), $search_lower)
            || str_contains(strtolower($row['lastname']), $search_lower)
            || str_contains(strtolower($row['email']), $search_lower);
    }));
}

// ─── Step 2: Total count (no extra DB query — just count the filtered array) ──
$total = count($all_users);

// ─── Step 3: Sort ─────────────────────────────────────────────────────────────
usort($all_users, function ($a, $b) use ($sort_by, $order) {
    $valA = strtolower((string) $a[$sort_by]);
    $valB = strtolower((string) $b[$sort_by]);

    $cmp = ($sort_by === 'id')
        ? ((int) $a[$sort_by] <=> (int) $b[$sort_by])   // numeric compare for id
        : strcmp($valA, $valB);                         // string compare for text cols

    return ($order === 'DESC') ? -$cmp : $cmp;
});

// ─── Step 4: Paginate ─────────────────────────────────────────────────────────
$total_pages = (int) ceil($total / $limit);
$page = min($page, max(1, $total_pages)); // clamp page to valid range
$offset = ($page - 1) * $limit;
$page_data = array_slice($all_users, $offset, $limit);

// ─── Step 5: Return JSON response ─────────────────────────────────────────────
// echo json_encode([
//     'success' => true,
//     'total' => $total,
//     'total_pages' => $total_pages,
//     'page' => $page,
//     'limit' => $limit,
//     'sort_by' => $sort_by,
//     'order' => $order,
//     'search' => $search,
//     'from_cache' => true,   // always true after first load; useful for debugging
//     'data' => $page_data,
// ]);

// Success Response Function
echo successResponse("Users fetched", $page_data, 200, [
    'total' => $total,
    'total_pages' => $total_pages,
    'page' => $page,
    'limit' => $limit,
    'sort_by' => $sort_by,
    'order' => $order,
    'search' => $search,
    'from_cache' => true,
]);
?>