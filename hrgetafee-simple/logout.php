<?php
// Place at: hrgetafee-simple/logout.php

require_once __DIR__ . '/includes/security_bootstrap.php';
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: login.php');
exit;
?>