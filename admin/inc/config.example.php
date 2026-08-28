<?php
/**
 * Smashbox dashboard — configuration.
 *
 * The admin lives at admin.smashboxproductions.com, whose document root is
 * <site>/admin. Everything it publishes is written one level up, into the
 * public site root.
 */

declare(strict_types=1);

// ---- database -------------------------------------------------------------
const DB_HOST = 'localhost';
const DB_NAME = 'CHANGEME_dbname';
const DB_USER = 'CHANGEME_dbuser';
const DB_PASS = 'CHANGEME_dbpass';

// ---- paths ----------------------------------------------------------------
define('ADMIN_DIR', dirname(__DIR__));
define('SITE_DIR', dirname(ADMIN_DIR));

define('TEMPLATE_FILE', SITE_DIR . '/template.html');
define('MANIFEST_FILE', SITE_DIR . '/cms-manifest.json');
define('PUBLISHED_FILE', SITE_DIR . '/index.html');
define('UPLOAD_DIR', SITE_DIR . '/uploads');

// public URL prefix for uploaded images, relative to the site root
const UPLOAD_URL = 'uploads/';

// ---- site -----------------------------------------------------------------
const SITE_URL = 'https://smashboxproductions.com/';
const STUDIO_EMAIL = 'smashboxindia@gmail.com';
const STUDIO_NAME = 'Smashbox Productions';

// ---- uploads --------------------------------------------------------------
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // matches php.ini upload_max_filesize
const ALLOWED_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'image/avif' => 'avif',
];

// ---- session --------------------------------------------------------------
const SESSION_NAME = 'sbx_admin';
const SESSION_IDLE_SECONDS = 60 * 60 * 8;
