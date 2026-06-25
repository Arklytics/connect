<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

Auth::logout();

header('Location: ' . app_url('business/login'));
exit();
