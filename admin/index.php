<?php
// The admin "home" is the Data Kost page.
require_once __DIR__ . '/../includes/auth.php';

requireAdmin(); // not logged in -> login page, logged in but not admin -> 403
redirect('/admin/boarding_houses.php');
