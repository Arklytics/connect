<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

Auth::logout();

header('Location: /wpi2/business/login');
exit();
