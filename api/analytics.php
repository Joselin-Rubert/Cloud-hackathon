<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();

respond(true, '', analytics_data(user_id()));
