<?php
// includes/bootstrap.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Model.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/CoreModels.php';
require_once __DIR__ . '/../models/GRNStockModels.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Helper.php';

// Start session
Session::start();
