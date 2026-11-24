<?php

return [
    // Anonymous requests, per IP.
    'guest_per_minute' => (int) env('RATE_LIMIT_PER_MINUTE', 60),

    // Signed-in customers / employees, per account (POS terminals and admin screens are chatty).
    'user_per_minute' => (int) env('RATE_LIMIT_USER_PER_MINUTE', 300),

    // Login attempts per email+IP, plus a looser per-IP cap against credential stuffing.
    'login_per_minute' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
    'login_ip_per_minute' => (int) env('RATE_LIMIT_LOGIN_IP_PER_MINUTE', 20),

    // CPU / network heavy endpoints (cover OCR, spreadsheet import, dataset sync), per account.
    'heavy_per_minute' => (int) env('RATE_LIMIT_HEAVY_PER_MINUTE', 6),
];
