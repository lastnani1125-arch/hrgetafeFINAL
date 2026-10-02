<?php
// Include this at top of pages requiring session/role checks.
// Place at: hrgetafee-simple/includes/security_bootstrap.php

require_once __DIR__ . '/security.php';

// Idle timeout (30 minutes)
$max_idle = 1800;
if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $max_idle) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();
?>