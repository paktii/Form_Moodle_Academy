<?php

return [
    'browser_path' => env('PDF_BROWSER_PATH'),
    'no_sandbox' => (bool) env('PDF_BROWSER_NO_SANDBOX', false),
    'timeout' => (int) env('PDF_BROWSER_TIMEOUT', 60),
];
