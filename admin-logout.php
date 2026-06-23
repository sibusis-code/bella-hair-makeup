<?php
require_once __DIR__ . '/admin-functions.php';

startAdminSession();
adminLogout();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="2; url=admin-login.php">
    <title>Logging Out...</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Montserrat', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .logout-container {
            text-align: center;
            background: white;
            padding: 3rem 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 400px;
        }
        
        .logout-container h1 {
            color: #1a1a1a;
            margin: 0 0 1rem 0;
        }
        
        .logout-container p {
            color: #888;
            margin: 0;
        }
        
        .logout-container a {
            color: #c9a961;
            text-decoration: none;
            font-weight: 600;
        }
        
        .logout-container a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <h1>Logged Out</h1>
        <p>You have been successfully logged out.</p>
        <p>Redirecting to login page...</p>
        <p><a href="admin-login.php">Click here if not redirected</a></p>
    </div>
</body>
</html>
