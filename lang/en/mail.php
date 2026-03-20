<?php

return [
    'common' => [
        'default_recipient_name' => 'User',
        'action_hint' => 'If the button does not work, copy and open this link',
    ],
    'welcome' => [
        'subject' => 'Welcome to :app',
        'heading' => 'Welcome, :name',
        'intro' => 'Your account has been created. Please verify your email address first.',
        'action_label' => 'Verify Email',
    ],
    'registration_invite' => [
        'subject' => ':app Registration Invitation',
        'heading' => 'You received a registration invitation',
        'intro' => 'Please use the link below to complete your registration.',
        'action_label' => 'Complete Registration',
    ],
    'password_reset' => [
        'subject' => ':app Password Reset',
        'heading' => 'Hello, :name',
        'intro' => 'We received a request to reset your password. Use the button below to continue.',
        'action_label' => 'Reset Password',
    ],
];
