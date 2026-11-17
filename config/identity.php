<?php

return [
    /*
     | LOCAL DEVELOPMENT ONLY. When true AND APP_ENV=local, newly added emails are marked verified
     | immediately so you can see avatars/badges without a mail server. It is ignored in every other
     | environment, because auto-verifying would let anyone claim any address.
     */
    'auto_verify_emails' => (bool) env('AUTO_VERIFY_EMAILS', false),
];
