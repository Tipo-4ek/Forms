<?php 
function logout() {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Destroy all session data
    $_SESSION = array();
    session_destroy();

    // Delete all cookies
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            // Set path and domain attributes for the setcookie function 
            $params = session_get_cookie_params();
            setcookie($name, '', time() - 1000, $params['path'], $params['domain']); 
        }
    }
}

logout();
echo "<script>window.location.replace('index.php');</script>";
?>