<?php
/**
 * MiksTraffic Authentication Helper
 */

session_start();

class Auth {
    public static function login($username, $password, $db) {
        $user = $db->getUserByUsername($username);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
        return false;
    }

    public static function check() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit();
        }
    }

    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
