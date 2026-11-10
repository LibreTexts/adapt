<?php
return [
    'is_me_cookie' => env('IS_ME_COOKIE', 'no is me cookie'),
    'telegram_channel_id' => env('TELEGRAM_CHANNEL_ID', 'no telegram channel id'),
    'webwork_seed' => env('WEBWORK_SEED', 'no webwork seed'),
    'webwork_token' => env('WEBWORK_TOKEN', false),
    'imathas_token' => env('IMATHAS_TOKEN', 'Imathas token is missing'),
    'imathas_seed' => env('IMATHAS_SEED', 'no imathas seed'),
    'jwt_secret' => env('JWT_SECRET', null),
    'webwork_jwt_secret' => env('WEBWORK_JWT_SECRET', file_exists(base_path() . '/JWE/webwork')
        ? file_get_contents(base_path() . '/JWE/webwork')
        : 'No string exists'),
    'db_host' => env('DB_HOST', 'No host provided'),
    'minpods' => env('MINPODS', 0),
    'loadtest' => env('LOADTEST', false),
    'my_ip' => env('MY_IP', false),
    'analytics_token' => env('ANALYTICS_TOKEN', false),
    'my_essay_feedback_token' => env('MY_ESSAY_FEEDBACK_TOKEN', false),
    'analytics_dashboard_token' => env('ANALYTICS_DASHBOARD_TOKEN', false),
    'libre_one_token' => env('LIBRE_ONE_TOKEN', false),
    'libretexts_private_page_token' => env('LIBRETEXTS_PRIVATE_PAGE_TOKEN', false),
    's3_bucket' => env('APP_VAPOR') ? env('AWS_VAPOR_BUCKET') : env('AWS_BUCKET'),
    'h5p_api_username' => env('H5P_API_USERNAME', ''),
    'h5p_api_password' => env('H5P_API_PASSWORD', ''),
    'centrifugo_secret_key' => env('CENTRIFUGO_SECRET_KEY', ''),
    'centrifugo_api_key' => env('CENTRIFUGO_API_KEY', ''),
    'centrifugo_domain' => env('CENTRIFUGO_DOMAIN', ''),
    'mysql8_db_password' => env('MYSQL8_DB_PASSWORD', ''),
    'openai_api_key' => env('OPENAI_API_KEY', ''),
    'analytics_user_id_api_key' => env('ANALYTICS_USER_ID_API_KEY', ''),
    'firebase_credentials' => env('FIREBASE_CREDENTIALS', ''),
    // LMS status monitor (check:lmsStatus): ids of the users emailed when a school's status changes.
    'lms_status_alert_user_ids' => env('LMS_STATUS_ALERT_USER_IDS', '1,5'),
    'lms_status_retention_hours' => (int)env('LMS_STATUS_RETENTION_HOURS', 72)
];
