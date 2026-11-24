<?php
/** FR-AUTH-02: sign out. POST + CSRF only, so another site cannot sign you out with a link or image. */
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
require_csrf();
if (is_logged_in()) { audit_log(db(), 'auth.logout', 'user', (int)auth_user()['id']); }
auth_clear_session();
flash('success', 'You are signed out.');
redirect('index.php');
