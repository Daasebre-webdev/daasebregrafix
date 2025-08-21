<?php
session_start();

// Clear all session variables
$_SESSION = [];

// Remove session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Also explicitly clear Google OAuth cookie if any
setcookie("g_state", "", time() - 3600, "/");

// Redirect to frontend
header("Location: http://localhost:3000/");
exit;
