<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (Auth::masterId() === null) {
    header('Location: /wpi2/master/login');
    exit();
}
