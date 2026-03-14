<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::destroy();
Helper::redirect('/sap-computers/login.php');
