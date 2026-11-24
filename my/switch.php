<?php
/** POST: choose which of your own profiles the site is showing. Someone else's id is "not found" and changes nothing. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
[$pdo] = my_guard();
require_csrf();
$id = is_string($_POST['profile'] ?? null) && ctype_digit($_POST['profile']) ? (int)$_POST['profile'] : 0;
if (!set_active_profile($pdo, $id)) { my_not_found(); }
redirect(my_safe_return($_POST['return'] ?? '', 'my/matches.php'));
