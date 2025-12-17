<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

'paths' => ['api/*', 'sanctum/csrf-cookie'],

'allowed_origins' => [
    // 'https://itap.qa',
    'https://admin.itap.qa',
    'http://34.18.138.112',
    'http://localhost:3000',
    'http://localhost:5173', 
],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true, 

];
