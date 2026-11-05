<?php
session_start();

require_once __DIR__ . '/scripts/sso_bridge.php';

eh_sso_clear();
session_destroy();
header('Location: ./');
exit();