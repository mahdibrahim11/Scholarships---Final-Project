<?php
/** AJAX (POST): change the application status of a saved scholarship on one of YOUR profiles. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
require_role([ROLE_STUDENT, ROLE_PARENT]);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_out(['error' => 'method_not_allowed'], 405); }
require_csrf();
$pdo = db();
$uid = (int)auth_user()['id'];
$pid = is_string($_POST['profile_id'] ?? null) && ctype_digit($_POST['profile_id']) ? (int)$_POST['profile_id'] : 0;
$sid = is_string($_POST['scholarship_id'] ?? null) && ctype_digit($_POST['scholarship_id']) ? (int)$_POST['scholarship_id'] : 0;
[$ok, $code] = saved_set_status($pdo, $uid, $pid, $sid, is_string($_POST['status'] ?? null) ? $_POST['status'] : '');
if ($code === 'not_found') { json_out(['error' => 'not_found'], 404); }
if (!$ok) { json_out(['error' => 'bad_status'], 422); }
json_out(['ok' => true, 'status' => $_POST['status']]);
