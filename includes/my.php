<?php
/** Shared bits for the family pages under /my/: guard, profile lookup, the child switcher, and "not found" answers. */
require_once __DIR__ . '/profiles.php';
require_once __DIR__ . '/search.php';

/** Every /my/ page starts with this. Students and parents only. */
function my_guard(): array
{
    require_role([ROLE_STUDENT, ROLE_PARENT]);
    return [db(), (int)auth_user()['id'], auth_user()['role']];
}

/** The same answer for "no such profile" and "someone else's profile". HTML pages. */
function my_not_found(string $what = 'profile'): never
{
    http_response_code(404);
    if (request_wants_json()) { json_out(['error' => 'not_found'], 404); }
    $pageTitle = 'Not found';
    require __DIR__ . '/header.php';
    echo '<h1>We could not find that ' . e($what) . '</h1><p>It may have been removed, or it is not on your account.</p>'
       . '<p><a class="btn btn-primary" href="' . e(url(auth_user()['role'] === ROLE_PARENT ? 'my/dashboard.php' : 'my/matches.php')) . '">Back to my scholarships</a></p>';
    require __DIR__ . '/footer.php';
    exit;
}

/**
 * The profile this page is about. If the address has ?profile=ID it must be one of the user's own (otherwise "not found");
 * if not, the active profile. When the user has no profile yet they are sent to create one.
 */
function my_profile_for_page(PDO $pdo, int $uid): array
{
    if (isset($_GET['profile'])) {
        $raw = $_GET['profile'];
        $id = is_string($raw) && ctype_digit($raw) ? (int)$raw : 0;
        if (!set_active_profile($pdo, $id)) { my_not_found(); }
    }
    $p = active_profile($pdo);
    if (!$p) { flash('info', 'Create a profile first, so we can find scholarships that fit.'); redirect('my/profile_edit.php'); }
    return $p;
}

/** For parents with more than one child: "Showing: [child v]". A POST to switch.php, so it needs no JavaScript. */
function child_switcher(array $profiles, array $active, string $return): string
{
    if (count($profiles) < 2) { return ''; }
    $opts = '';
    foreach ($profiles as $p) { $opts .= '<option value="' . (int)$p['id'] . '"' . ((int)$p['id'] === (int)$active['id'] ? ' selected' : '') . '>' . e($p['display_name']) . '</option>'; }
    return '<form method="post" action="' . e(url('my/switch.php')) . '" class="d-flex align-items-end gap-2 mb-3">' . csrf_field()
        . '<input type="hidden" name="return" value="' . e($return) . '">'
        . '<div><label class="form-label mb-0" for="child-switch">Showing</label><select class="form-select" id="child-switch" name="profile">' . $opts . '</select></div>'
        . '<button class="btn btn-outline-primary" type="submit">Switch</button></form>';
}

/** Only our own /my/ or search pages are allowed as a place to return to. */
function my_safe_return(?string $r, string $fallback): string
{
    $r = (string)$r;
    if (preg_match('#^(my/[a-z_]+\.php|search\.php|scholarship\.php)(\?[A-Za-z0-9_=&%.,\[\]+-]*)?$#', $r) && !str_contains($r, '..')) { return $r; }
    return $fallback;
}

/** A compact card for one child with counts by status. */
function status_counts(PDO $pdo, array $profile): array
{
    $st = $pdo->prepare('SELECT app_status, COUNT(*) FROM saved_scholarships WHERE profile_id = ? GROUP BY app_status');
    $st->execute([(int)$profile['id']]);
    $by = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $out = [];
    foreach (APP_STATUSES as $s) { $out[$s] = (int)($by[$s] ?? 0); }
    return $out;
}
