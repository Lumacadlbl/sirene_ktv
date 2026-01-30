<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to landing page
header("Location: landingpage.php"); // Adjust path if logout.php is in pages/
exit;
?>
