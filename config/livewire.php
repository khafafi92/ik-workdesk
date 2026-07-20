<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Livewire handles file uploads by first storing them in a temporary
    | directory. These settings control the behavior of temporary file uploads.
    |
    | - `disk`:       The disk to use for temporary file uploads.
    | - `directory`:  The directory within the disk to use for temporary file uploads.
    | - `rules`:      Validation rules applied to all temporary file uploads.
    |                 'file|max:102400' allows up to 100MB per file.
    | - `middleware`:  Middleware to apply to the temporary file upload endpoint.
    | - `preview_mimes`: MIME types that can be previewed in the browser.
    | - `max_upload_time`: Maximum time in minutes for a file upload to complete.
    | - `cleanup`:    Whether old temporary files should be cleaned up automatically.
    |
    */

    'temporary_file_upload' => [
        'disk' => null,             // Uses the default disk
        'directory' => null,        // Uses the default directory
        'rules' => 'file|max:102400', // 100MB max per file (in kilobytes)
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,     // 5 minutes max upload time
        'cleanup' => true,
    ],

];
