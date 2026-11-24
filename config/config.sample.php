<?php
// Copy this file to config/config.php and fill in your own values.
// config/config.php is listed in .gitignore. NEVER commit it (NFR-02).
return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'scholarships',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'            => 'Pathways to Scholarships',
        'base_url'        => '/scholarships',   // '' if the app lives at the web root
        'env'             => 'development',     // use 'production' on any public server (hides error details)
        'session_timeout' => 1800,              // seconds of inactivity before sign-out (FR-AUTH-04)
        'max_upload_mb'   => 5,
    ],
];
