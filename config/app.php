<?php

return [
    'env' => getenv('APP_ENV') ?: 'local',
    'name' => getenv('APP_NAME') ?: 'USORecords',
    'api_token' => getenv('API_TOKEN'),
];
