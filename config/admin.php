<?php

return [
    // When true, administrators must have two-step sign-in turned on before the admin area opens (recommended in production).
    'require_two_factor' => (bool) env('ADMIN_REQUIRE_2FA', false),
];
