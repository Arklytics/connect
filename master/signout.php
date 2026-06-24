<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';

Auth::logoutMaster();

header("Location: /wpi2/master/login");
exit;
