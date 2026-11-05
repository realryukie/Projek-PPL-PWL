<?php
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
redirect('/admin/boarding_houses.php');
