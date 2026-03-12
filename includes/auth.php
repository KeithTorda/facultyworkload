<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Check if user has admin role
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// Check if user has faculty role
function isFaculty() {
    return isLoggedIn() && $_SESSION['user_role'] === 'faculty';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}

// Redirect if not faculty
function requireFaculty() {
    if (!isFaculty()) {
        header('Location: ../index.php');
        exit();
    }
}

// Login user
function loginUser($user_id, $user_name, $user_email, $user_role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $user_name;
    $_SESSION['user_email'] = $user_email;
    $_SESSION['user_role'] = $user_role;
}

// Logout user
function logoutUser() {
    session_unset();
    session_destroy();
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ];
}
?> 