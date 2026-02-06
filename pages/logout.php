<?php
session_start();

// Clear all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear browser cache to prevent back button access
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// JavaScript to clear browser history (prevents back button)
echo '<script type="text/javascript">
    window.history.forward();
    function noBack() {
        window.history.forward();
    }
</script>';

// Redirect to landing page with delay
header("Refresh: 0; url=landingpage.php");
exit();
?>