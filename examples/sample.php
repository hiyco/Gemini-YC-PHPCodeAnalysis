<?php

/**
 * Sample PHP file with various issues for testing
 * This file intentionally contains security vulnerabilities and code quality issues
 */

class UserController
{
    private $db;
    
    public function __construct()
    {
        // Security issue: Direct database connection without proper configuration
        $this->db = new mysqli("localhost", "root", "", "mydb");
    }
    
    // Security vulnerability: SQL Injection
    public function getUserById($id)
    {
        // Dangerous: Direct SQL query with user input
        $query = "SELECT * FROM users WHERE id = " . $id;
        $result = mysql_query($query); // Deprecated function
        
        return $result;
    }
    
    // Security vulnerability: SQL Injection via string concatenation
    public function searchUsers($search)
    {
        $query = "SELECT * FROM users WHERE name LIKE '%" . $search . "%'";
        return $this->db->query($query);
    }
    
    // Code quality issue: Unused variable
    public function processUser($userData)
    {
        $timestamp = time(); // Unused variable
        $processedData = $this->validateUserData($userData);
        
        return $processedData;
    }
    
    // Security issue: Potential XSS vulnerability
    public function displayUserProfile($user)
    {
        echo "<h1>Welcome " . $user['name'] . "</h1>"; // Unescaped output
        echo "<p>Email: " . $user['email'] . "</p>";   // Unescaped output
    }
    
    // Performance issue: Inefficient loop
    public function calculateStats($users)
    {
        $stats = [];
        foreach ($users as $user) {
            // Inefficient: Making database call in loop
            $profile = $this->db->query("SELECT * FROM profiles WHERE user_id = " . $user['id']);
            $stats[] = $profile;
        }
        return $stats;
    }
    
    // Security issue: Using eval() - dangerous function
    public function executeCommand($command)
    {
        $result = eval($command); // Extremely dangerous
        return $result;
    }
    
    // Code quality: Long line exceeding typical limits
    public function aVeryLongMethodNameThatExceedsTypicalLineLengthLimitsAndShouldBeRefactoredToSomethingShorterAndMoreMeaningful($param1, $param2, $param3, $param4)
    {
        return $param1 + $param2 + $param3 + $param4;
    }
    
    private function validateUserData($data)
    {
        // Minimal validation - could be improved
        return $data;
    }
}