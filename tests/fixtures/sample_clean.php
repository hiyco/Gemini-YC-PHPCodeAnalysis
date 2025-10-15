<?php

declare(strict_types=1);

/**
 * Test fixture: Clean PHP file with no issues
 * Used for testing that clean code passes analysis
 */

class CleanUserController
{
    private \PDO $database;
    
    public function __construct(\PDO $database)
    {
        $this->database = $database;
    }
    
    // Safe database query with prepared statements
    public function getUserById(int $id): ?array
    {
        $stmt = $this->database->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    // Safe output with proper escaping
    public function displayWelcome(string $username): string
    {
        return '<h1>Welcome ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</h1>';
    }
    
    // Safe command execution with validation
    public function executeAllowedCommand(string $command): ?string
    {
        $allowedCommands = ['ls', 'pwd', 'whoami'];
        
        if (!in_array($command, $allowedCommands, true)) {
            throw new \InvalidArgumentException('Command not allowed');
        }
        
        return shell_exec(escapeshellcmd($command));
    }
    
    // Safe file inclusion with validation
    public function loadTemplate(string $templateName): void
    {
        $allowedTemplates = ['header', 'footer', 'sidebar'];
        
        if (!in_array($templateName, $allowedTemplates, true)) {
            throw new \InvalidArgumentException('Template not allowed');
        }
        
        $templatePath = __DIR__ . '/templates/' . $templateName . '.php';
        
        if (!file_exists($templatePath)) {
            throw new \RuntimeException('Template not found');
        }
        
        include $templatePath;
    }
    
    // Safe deserialization with allowed classes
    public function processData(string $serializedData): object
    {
        $allowedClasses = ['stdClass', 'UserData', 'Configuration'];
        
        return unserialize($serializedData, ['allowed_classes' => $allowedClasses]);
    }
    
    // Alternative to eval - using a parser approach
    public function evaluateMathExpression(string $expression): float
    {
        // Simple math expression evaluator (safe alternative to eval)
        if (!preg_match('/^[\d\+\-\*\/\(\)\.\s]+$/', $expression)) {
            throw new \InvalidArgumentException('Invalid math expression');
        }
        
        // Using a safe math parser instead of eval
        return $this->safeMathEvaluator($expression);
    }
    
    private function safeMathEvaluator(string $expression): float
    {
        // Simplified safe math evaluator
        // In real implementation, use a proper math expression parser
        return 0.0;
    }
}