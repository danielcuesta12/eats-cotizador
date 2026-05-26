<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    redirect('/admin/dashboard.php');
} else {
    redirect('/auth/login.php');
}
