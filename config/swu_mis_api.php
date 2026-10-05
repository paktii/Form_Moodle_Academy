<?php

return [
    'base_url' => env('SWU_MIS_API_BASE_URL', 'https://api.swu.ac.th/mis/v1/'),
    'username' => env('SWU_MIS_API_USERNAME'),
    'password' => env('SWU_MIS_API_PASSWORD'),
    'timeout' => (int) env('SWU_MIS_API_TIMEOUT', 10),
    'connect_timeout' => (int) env('SWU_MIS_API_CONNECT_TIMEOUT', 5),
    'page_size' => (int) env('SWU_MIS_API_PAGE_SIZE', 100),

    'endpoints' => [
        'departments' => 'Department',
        'officers' => 'Officer',
        'students' => 'Student',
    ],
];
