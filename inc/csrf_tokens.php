<?php

/**
 * Update the CSRF token
 */
function updateCsrf() {
    if(!sessionExists()) {
        session_start();
    }

    $_SESSION['csrf_token'] = generateCsrf();
}

/**
 * Check whether a session is currently active
 * @return bool
 */
function sessionExists() {
    if (version_compare(phpversion(), '5.4.0', '>')) {
        return session_id() !== '';
    } else {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}

/**
 * Generate a CSRF token, it chooses from 3 different functions based on PHP version and modules
 * @return bool|string
 */
function generateCsrf() {
    if (version_compare(phpversion(), '7.0.0', '>=')) {
        $random = generateRandom();
        if($random !== false) return $random;
    }

    if (function_exists('mcrypt_create_iv')) {
        return generateMcrypt();
    }

    return generateOpenssl();
}

/**
 * Generate a random string using the PHP built in function random_bytes
 * @version 7.0.0
 * @return bool|string
 */
function generateRandom() {
    try {
        return bin2hex(random_bytes(32));
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate a random string using MCRYPT
 * @return string
 */
function generateMcrypt() {
    return bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
}

/**
 * Generate a random string using OpenSSL
 * @return string
 */
function generateOpenssl() {
    return bin2hex(openssl_random_pseudo_bytes(32));
}

/**
 * Checks for session and the existence of a key
 * after ensuring both are present it returns the
 * current CSRF token
 * @return string
 */
function getCsrf() {
    if(!sessionExists()) {
        session_start();
    }

    if(!array_key_exists('csrf_token', $_SESSION)) {
        updateCsrf();
    }

    return $_SESSION['csrf_token'];
}

/**
 * Get a hidden CSRF input field to be used in forms
 * @return string
 */
function getCsrfField() {
    return sprintf("<input type=\"hidden\" name=\"csrf_token\" value=\"%s\">", getCsrf());
}

/**
 * Verify a given CSRF token
 * @param $csrf_token
 * @return bool
 */
function verifyCsrf($csrf_token) {
    $current_csrf = getCsrf();
    // Prefer hash_equals over === because the latter is vulnerable to timing attacks
    if(function_exists('hash_equals')) {
        return hash_equals($current_csrf, $csrf_token);
    }

    return false;
}

/**
 * Uses the global $_POST variable to check if the CSRF token is valid
 * @return bool
 */
function verifyCsrfPost() {
    return (isset($_POST['csrf_token']) && verifyCsrf($_POST['csrf_token']));
}