<?php

return [
    'driver' => getenv('STORAGE_DRIVER') ?: 'local',
    'local' => [
        'path' => getenv('LOCAL_STORAGE_PATH') ?: __DIR__ . '/../storage',
    ],
    'gcs' => [
        'project_id' => getenv('GCS_PROJECT_ID'),
        'bucket' => getenv('GCS_BUCKET') ?: 'usorecords-prod',
        'audio_path' => getenv('GCS_AUDIO_PATH') ?: 'audio',
        'key_file' => getenv('GCS_KEY_FILE'), // Path to service account JSON key
    ],
];
