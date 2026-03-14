<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sap_computers');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'SAP Computers IMS');
define('APP_URL', 'http://localhost/sap-computers');
define('APP_VERSION', '1.0.0');

// Session
define('SESSION_NAME', 'sap_ims_session');
define('SESSION_LIFETIME', 3600 * 8);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Low stock threshold override
define('LOW_STOCK_GLOBAL', 5);
