<?php
/**
 * ACCESS CONTROL STUBS (Iterations 2-6).
 *
 * These do nothing yet, on purpose. Call require_role() at the top of EVERY admin page now, so that
 * in Iteration 7 you implement the body ONCE and the whole site is protected. See the spec's
 * "Open-First Development Approach".  Remember: hiding a menu link is not access control (FR-AUTH-05).
 *
 * TODO(Iteration 7): real login, session regeneration, inactivity timeout, role lookup from the
 * database, 403 page, and removal of the development banner.
 */

const ROLE_VISITOR = 'visitor';
const ROLE_STUDENT = 'student';
const ROLE_PARENT = 'parent';
const ROLE_ADMIN = 'administrator';
const ROLE_SUPER_ADMIN = 'super_admin';

/** @param string[] $roles roles allowed to see the page, e.g. [ROLE_ADMIN, ROLE_SUPER_ADMIN] */
function require_role(array $roles): void
{
    // STUB: allows everyone. Implemented in Iteration 7.
}

/** The acting user. Until Iteration 7 this is the placeholder development user. */
function current_user_id(): int
{
    return (int)config('app.dev_user_id', 1);
}

/** Role used by the navigation. Until Iteration 7 the nav shows everything. */
function current_role(): string
{
    return ROLE_SUPER_ADMIN;
}

function is_dev_mode(): bool
{
    return true;   // TODO(Iteration 7): return false
}
