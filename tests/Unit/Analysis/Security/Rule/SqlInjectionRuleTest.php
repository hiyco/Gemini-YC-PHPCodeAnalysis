<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Unit tests for SQL Injection Rule
 */

namespace YcPca\Tests\Unit\Analysis\Security\Rule;

use YcPca\Tests\TestCase;
use YcPca\Analysis\Security\Rule\SqlInjectionRule;
use YcPca\Analysis\Issue\Issue;
use YcPca\Tests\Helpers\TestHelper;
use PhpParser\Node;
use PhpParser\ParserFactory;

/**
 * Test SQL Injection Rule functionality
 * 
 * @covers \YcPca\Analysis\Security\Rule\SqlInjectionRule
 */
class SqlInjectionRuleTest extends TestCase
{
    private SqlInjectionRule $rule;
    private \PhpParser\Parser $phpParser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->rule = new SqlInjectionRule();
        $this->phpParser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function testRuleBasicProperties(): void
    {
        $this->assertEquals('sql_injection', $this->rule->getRuleId());
        $this->assertEquals('SQL Injection Detection', $this->rule->getRuleName());
        $this->assertStringContainsString('SQL injection', $this->rule->getDescription());
        $this->assertEquals('sql_injection', $this->rule->getVulnerabilityType());
        $this->assertEquals('A03_injection', $this->rule->getOwaspCategory());
        $this->assertEquals(Issue::SEVERITY_HIGH, $this->rule->getRiskLevel());
        $this->assertContains(89, $this->rule->getCweIds()); // CWE-89: SQL Injection
        $this->assertTrue($this->rule->isEnabled());
    }

    public function testDetectDirectSqlInjectionInMysqlQuery(): void
    {
        $code = '<?php
function getUserById($id) {
    $query = "SELECT * FROM users WHERE id = " . $id;
    return mysql_query($query);
}';
        
        $context = $this->createFileContext($code);
        $ast = $this->phpParser->parse($code);
        
        $issues = [];
        foreach ($ast as $node) {
            $this->collectIssuesFromNode($node, $context, $issues);
        }
        
        $this->assertGreaterThan(0, count($issues));
        
        // Check for SQL injection issue
        $sqlInjectionFound = false;
        foreach ($issues as $issue) {
            if ($issue->getRuleId() === 'sql_injection' && 
                strpos($issue->getTitle(), 'SQL Injection') !== false) {
                $sqlInjectionFound = true;
                $this->assertEquals(Issue::SEVERITY_HIGH, $issue->getSeverity());
                break;
            }
        }
        
        $this->assertTrue($sqlInjectionFound, 'SQL injection issue should be detected');
    }

    public function testDetectSqlInjectionInStringConcatenation(): void
    {
        $code = '<?php
function searchUsers($search) {
    $query = "SELECT * FROM users WHERE name LIKE \'%" . $search . "%\'";
    return mysqli_query($connection, $query);
}';
        
        $context = $this->createFileContext($code);
        $ast = $this->phpParser->parse($code);
        
        $issues = [];
        foreach ($ast as $node) {
            $this->collectIssuesFromNode($node, $context, $issues);
        }
        
        $this->assertIssueExists($issues, [
            'rule_id' => 'sql_injection',
            'severity' => Issue::SEVERITY_HIGH
        ]);
    }

    public function testDetectSqlInjectionInPdoQuery(): void
    {
        $code = '<?php
class UserRepository {
    public function findUser($id) {
        $query = "SELECT * FROM users WHERE id = " . $id;
        return $this->pdo->query($query);
    }
}';
        
        $context = $this->createFileContext($code);
        $ast = $this->phpParser->parse($code);
        
        $issues = [];
        foreach ($ast as $node) {
            $this->collectIssuesFromNode($node, $context, $issues);
        }
        
        $this->assertIssueExists($issues, [
            'rule_id' => 'sql_injection',
            'severity' => Issue::SEVERITY_HIGH
        ]);
    }

    public function testNoFalsePositiveForPreparedStatements(): void
    {
        $code = '<?php
function getUserById($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}';
        
        $context = $this->createFileContext($code);
        $ast = $this->phpParser->parse($code);
        
        $issues = [];
        foreach ($ast as $node) {
            $this->collectIssuesFromNode($node, $context, $issues);
        }
        
        // Should not detect SQL injection in prepared statements
        $sqlInjectionIssues = array_filter($issues, function($issue) {
            return $issue->getRuleId() === 'sql_injection';
        });
        
        $this->assertEmpty($sqlInjectionIssues, 'No SQL injection should be detected in prepared statements');
    }

    public function testDetectVariableInterpolation(): void
    {
        $code = '<?php
function getUserData($userId) {
    $query = "SELECT * FROM users WHERE id = $userId";
    return mysql_query($query);
}';
        
        $context = $this->createFileContext($code);
        $ast = $this->phpParser->parse($code);
        
        $issues = [];
        foreach ($ast as $node) {
            $this->collectIssuesFromNode($node, $context, $issues);
        }
        
        $this->assertIssueExists($issues, [
            'rule_id' => 'sql_injection'
        ]);
    }

    public function testDeprecatedMysqlFunctionDetection(): void
    {
        $code = '<?php
function legacyQuery($query) {
    return mysql_query($query);
}';
        
        $context = $this->createFileContext($code);
        $ast = $this->phpParser->parse($code);
        
        $issues = [];
        foreach ($ast as $node) {
            $this->collectIssuesFromNode($node, $context, $issues);
        }
        
        $this->assertIssueExists($issues, [
            'rule_id' => 'sql_injection',
            'severity' => Issue::SEVERITY_HIGH
        ]);
    }

    public function testConfigurationOptions(): void
    {
        // Test with MySQL functions disabled
        $config = [
            'check_mysql_functions' => false,
            'check_pdo_methods' => true
        ];
        
        $rule = new SqlInjectionRule($config);
        
        $this->assertFalse($rule->getConfigValue('check_mysql_functions'));
        $this->assertTrue($rule->getConfigValue('check_pdo_methods'));
    }

    public function testRemediationSuggestions(): void
    {
        $suggestions = $this->rule->getRemediationSuggestions();
        
        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
        
        $suggestionsText = implode(' ', $suggestions);
        $this->assertStringContainsString('prepared statements', $suggestionsText);
        $this->assertStringContainsString('parameter binding', $suggestionsText);
    }

    public function testGetBestPractices(): void
    {
        $practices = $this->rule->getBestPractices();
        
        $this->assertIsArray($practices);
        $this->assertNotEmpty($practices);
        
        $practicesText = implode(' ', $practices);
        $this->assertStringContainsString('prepared statements', $practicesText);
        $this->assertStringContainsString('validate input', $practicesText);
    }

    public function testPriorityAndTags(): void
    {
        $this->assertEquals(90, $this->rule->getPriority());
        
        $tags = $this->rule->getTags();
        $this->assertContains('security', $tags);
        $this->assertContains('sql_injection', $tags);
        $this->assertContains('injection', $tags);
        $this->assertContains('database', $tags);
    }

    public function testSupportedNodeTypes(): void
    {
        $nodeTypes = $this->rule->getSupportedNodeTypes();
        
        $this->assertContains('Expr_FuncCall', $nodeTypes);
        $this->assertContains('Expr_MethodCall', $nodeTypes);
        $this->assertContains('Scalar_String', $nodeTypes);
    }

    public function testAppliesToNodeType(): void
    {
        $this->assertTrue($this->rule->appliesToNodeType('Expr_FuncCall'));
        $this->assertTrue($this->rule->appliesToNodeType('Expr_MethodCall'));
        $this->assertTrue($this->rule->appliesToNodeType('Scalar_String'));
        $this->assertFalse($this->rule->appliesToNodeType('Stmt_Class'));
    }

    public function testRuleReset(): void
    {
        $rule = $this->rule->reset();
        $this->assertInstanceOf(SqlInjectionRule::class, $rule);
        $this->assertEquals($this->rule, $rule); // Should return self
    }

    public function testComplexSqlInjectionPattern(): void
    {
        $code = '<?php
class DatabaseManager {
    public function complexQuery($userId, $status, $search) {
        $where = [];
        if ($userId) {
            $where[] = "user_id = " . $userId;
        }
        if ($status) {
            $where[] = "status = \'" . $status . "\'";
        }
        if ($search) {
            $where[] = "title LIKE \'%" . $search . "%\'";
        }
        
        $query = "SELECT * FROM posts WHERE " . implode(" AND ", $where);
        return $this->db->query($query);
    }
}';
        
        $context = $this->createFileContext($code);
        $ast = $this->phpParser->parse($code);
        
        $issues = [];
        foreach ($ast as $node) {
            $this->collectIssuesFromNode($node, $context, $issues);
        }
        
        // Should detect multiple SQL injection vulnerabilities
        $sqlInjectionIssues = array_filter($issues, function($issue) {
            return $issue->getRuleId() === 'sql_injection';
        });
        
        $this->assertGreaterThan(1, count($sqlInjectionIssues));
    }

    /**
     * Helper method to recursively collect issues from AST nodes
     */
    private function collectIssuesFromNode(Node $node, $context, array &$issues): void
    {
        $nodeIssues = $this->rule->validate($node, $context);
        $issues = array_merge($issues, $nodeIssues);
        
        // Recursively check child nodes
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            
            if ($subNode instanceof Node) {
                $this->collectIssuesFromNode($subNode, $context, $issues);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $arrayNode) {
                    if ($arrayNode instanceof Node) {
                        $this->collectIssuesFromNode($arrayNode, $context, $issues);
                    }
                }
            }
        }
    }
}