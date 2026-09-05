<?php

return [
    'csp_safe' => true,

    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:51200'],
    ],
];
