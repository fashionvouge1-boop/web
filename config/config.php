<?php
/**
 * StyleHub - global configuration
 * ---------------------------------------------------------------
 * Edit the database credentials and payment keys below.
 */

// ---------- Database ----------
// Credentials are read from environment variables when present (e.g. on Render),
// otherwise they fall back to the local XAMPP defaults below.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'stylehub');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');   // XAMPP default is an empty password. Set 'root' if needed.

// ---------- Site ----------
define('SITE_NAME',    'StyleHub');
define('SITE_TAGLINE', 'Fashion for Him & Her');
define('CURRENCY',     '₹');
define('SHIPPING_FLAT', 99.00);      // flat shipping fee
define('FREE_SHIPPING_OVER', 1500.00); // free shipping for orders above this amount

// ---------- Email ----------
define('MAIL_FROM',      'no-reply@stylehub.test');
define('MAIL_FROM_NAME', SITE_NAME);
define('SUPPORT_EMAIL',  'support@stylehub.test');
define('SUPPORT_PHONE',  '+91 98765 43210');

// ---------- Paths ----------
define('ROOT_PATH', dirname(__DIR__));         // filesystem path of the project root

// Auto-detect the URL base path (works with the PHP built-in server AND XAMPP sub-folders)
$__docroot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$__root    = str_replace('\\', '/', ROOT_PATH);
$__base    = ($__docroot && strpos($__root, $__docroot) === 0)
    ? rtrim(substr($__root, strlen($__docroot)), '/')
    : '';
define('BASE_PATH', $__base);                  // e.g. '' or '/stylehub'

// ---------- Payment gateways ----------
/**
 * Add your live/test keys here and enable the gateway you want.
 * The application ships with a working "Demo" gateway (sandbox) and
 * Cash-on-Delivery so you can test the whole flow with NO keys.
 */
$GATEWAYS = [
    'cod' => [
        'label'   => 'Cash on Delivery',
        'enabled' => true,
        'mode'    => 'offline',
    ],
    'demo' => [
        'label'   => 'Demo Card / UPI (Test gateway)',
        'enabled' => true,
        'mode'    => 'sandbox',   // simulates a successful payment
    ],
    'razorpay' => [
        'label'   => 'Razorpay',
        'enabled' => false,       // set to true after adding keys
        'mode'    => 'test',      // test | live
        'key_id'  => '',
        'key_secret' => '',
    ],
    'stripe' => [
        'label'   => 'Stripe',
        'enabled' => false,
        'mode'    => 'test',
        'publishable_key' => '',
        'secret_key'      => '',
    ],
];

// ---------- Cash on Delivery ----------
/**
 * Cash on Delivery is shown to shoppers but NOT deliverable, so customers
 * are pushed towards an online payment method.
 */
define('COD_BLOCKED', true);
define('COD_BLOCK_MESSAGE', 'cannot deliver in cash on delivery option in your pin code 📍');

// ---------- Session ----------
if (session_status() === PHP_SESSION_NONE) {
    // Keep session files outside the web root, in a writable temp directory.
    $__sessionDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'stylehub_sessions';
    if (!is_dir($__sessionDir)) {
        @mkdir($__sessionDir, 0775, true);
    }
    if (is_dir($__sessionDir) && is_writable($__sessionDir)) {
        session_save_path($__sessionDir);
    }
    session_start();
}

// ---------- Error reporting (turn OFF display_errors in production) ----------
// Set APP_DEBUG=1 in the environment to show errors (handy while testing on Render).
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === '1' ? '1' : '0');
date_default_timezone_set('Asia/Kolkata');
