<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
\App\Utils\EnvLoader::load(__DIR__ . '/../.env');

require_once __DIR__ . '/../routes/api.php';