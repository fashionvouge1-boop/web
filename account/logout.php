<?php
require_once __DIR__ . '/../includes/functions.php';

unset($_SESSION['user_id']);
set_flash('info', 'You have been signed out.');
redirect('index.php');
