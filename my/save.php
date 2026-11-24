<?php
/** AJAX (POST): save or un-save one scholarship for one of YOUR profiles. Anyone else's profile id answers 404 and changes nothing. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
require_role([ROLE_STUDENT, ROLE_PARENT]);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_out(['error' => 'method_not_allowed'], 405); }
require_csrf();
$pdo = db();
$uid = (int)auth_user()['id'];
$pid = is_string($_POST['profile'] ?? null) && ctype_digit($_POST['profile']) ? (int)$_POST['profile'] : 0;
$sid = is_string($_POST['scholarship'] ?? null) && ctype_digit($_POST['scholarship']) ? (int)$_POST['scholarship'] : 0;
$save = ($_POST['save'] ?? '') === '1';
[$ok, $code] = saved_set($pdo, $uid, $pid, $sid, $save);
if (!$ok) { json_out(['error' => 'not_found'], 404); }
json_out(['ok' => true, 'saved' => $code === 'saved', 'scholarship' => $sid, 'profile' => $pid]);
