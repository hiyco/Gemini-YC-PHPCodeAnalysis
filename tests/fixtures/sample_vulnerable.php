<?php

/**
 * Test fixture: PHP file with various security vulnerabilities
 * Used for testing security analysis functionality
 */

class VulnerableUserController
{
    private $database;
    
    public function __construct()
    {
        $this->database = new mysqli("localhost", "user", "pass", "db");
    }
    
    // SQL Injection vulnerability
    public function getUserById($id)
    {
        $query = "SELECT * FROM users WHERE id = " . $id;
        return mysql_query($query);
    }
    
    // XSS vulnerability
    public function displayWelcome($username)
    {
        echo "<h1>Welcome " . $username . "</h1>";
    }
    
    // Command injection vulnerability
    public function executeCommand($cmd)
    {
        return shell_exec($cmd);
    }
    
    // File inclusion vulnerability
    public function loadTemplate($template)
    {
        include $template . '.php';
    }
    
    // Unsafe deserialization
    public function processData($serializedData)
    {
        return unserialize($serializedData);
    }
    
    // Eval usage (dangerous)
    public function evaluateExpression($expression)
    {
        return eval($expression);
    }
}