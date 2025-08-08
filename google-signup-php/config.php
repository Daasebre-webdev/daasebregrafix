<?php
// Central configuration for database, Google OAuth, SMTP, and reCAPTCHA
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'project_selector',
        'user' => 'root',
        'pass' => ''
    ],
    'google' => [
        'client_id' => '343168144505-0fhp2lht81n2d09jfokou2alsgfbqmm7.apps.googleusercontent.com',
        'client_secret' => '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',
        'redirect_uri' => 'http://localhost/Google_signup/index.php'
    ],
    'smtp' => [
        'username' => 'uccmarket848@gmail.com',
        'password' => 'btur fkly mtpd edmi' // Replace with your Gmail App Password
    ],
    'recaptcha' => [
        'site_key' => '6LdecGYrAAAAAKnkc61iivG44inWC0hjqkVXMubj',
        'secret_key' => '6LdecGYrAAAAAKtzsfjTAhIiR7LlPHrzjCWqyXPK'
    ]
];
?>