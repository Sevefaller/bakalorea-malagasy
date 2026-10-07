<?php

return [
    'username' => env('ADMIN_USERNAME', 'Faller'),
    // Static credential for the first admin space; only its bcrypt hash is stored here.
    'password_hash' => env('ADMIN_PASSWORD_HASH', '$2y$12$Jup.ZdULMA.3jHPL9pE23uKBou5oVToSPxZP0tidNN6PAufj21Fue'),
    'cookie' => 'bakalorea_admin',
    'finished_retention_days' => (int) env('ADMIN_FINISHED_RETENTION_DAYS', 30),
];
