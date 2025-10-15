<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Issue model representing a found code problem
 */

namespace YcPca\Analysis\Issue;

/**
 * Represents a code issue found during analysis
 * 
 * Contains:
 * - Issue identification and location
 * - Severity and category classification
 * - Detailed description and suggestions
 * - Fix recommendations
 */
class Issue
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_INFO = 'info';

    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_PERFORMANCE = 'performance';
    public const CATEGORY_QUALITY = 'quality';
    public const CATEGORY_SYNTAX = 'syntax';
    public const CATEGORY_STYLE = 'style';
    public const CATEGORY_MAINTAINABILITY = 'maintainability';
    public const CATEGORY_COMPATIBILITY = 'compatibility';

    private \DateTime $createdAt;

    public function __construct(
        private string $id,
        private string $title,
        private string $description,
        private string $severity = self::SEVERITY_MEDIUM,
        private string $category = self::CATEGORY_QUALITY,
        private ?int $line = null,
        private ?int $column = null,
        private ?int $endLine = null,
        private ?int $endColumn = null,
        private ?string $ruleId = null,
        private ?string $ruleName = null,
        private array $tags = [],
        private array $suggestions = [],
        private ?string $codeSnippet = null,
        private array $metadata = []
    ) {
        $this->createdAt = new \DateTime();
        $this->validateSeverity($severity);
        $this->validateCategory($category);
    }

    /**
     * Get unique issue ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get issue title/summary
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get detailed description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get issue severity
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Get issue category
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Get line number where issue starts
     */
    public function getLine(): ?int
    {
        return $this->line;
    }

    /**
     * Get column number where issue starts
     */
    public function getColumn(): ?int
    {
        return $this->column;
    }

    /**
     * Get line number where issue ends
     */
    public function getEndLine(): ?int
    {
        return $this->endLine;
    }

    /**
     * Get column number where issue ends
     */
    public function getEndColumn(): ?int
    {
        return $this->endColumn;
    }

    /**
     * Get rule ID that detected this issue
     */
    public function getRuleId(): ?string
    {
        return $this->ruleId;
    }

    /**
     * Get rule name that detected this issue
     */
    public function getRuleName(): ?string
    {
        return $this->ruleName;
    }

    /**
     * Get issue tags
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Add a tag
     */
    public function addTag(string $tag): self
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    /**
     * Get fix suggestions
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Add a fix suggestion
     */
    public function addSuggestion(string $suggestion): self
    {
        $this->suggestions[] = $suggestion;
        return $this;
    }

    /**
     * Get code snippet showing the issue
     */
    public function getCodeSnippet(): ?string
    {
        return $this->codeSnippet;
    }

    /**
     * Set code snippet
     */
    public function setCodeSnippet(string $snippet): self
    {
        $this->codeSnippet = $snippet;
        return $this;
    }

    /**
     * Get additional metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Add metadata entry
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Check if issue has location information
     */
    public function hasLocation(): bool
    {
        return $this->line !== null;
    }

    /**
     * Get location string representation
     */
    public function getLocationString(): string
    {
        if (!$this->hasLocation()) {
            return 'No location';
        }
        
        $location = "Line {$this->line}";
        
        if ($this->column !== null) {
            $location .= ", Column {$this->column}";
        }
        
        if ($this->endLine !== null && $this->endLine !== $this->line) {
            $location .= " - Line {$this->endLine}";
            
            if ($this->endColumn !== null) {
                $location .= ", Column {$this->endColumn}";
            }
        }
        
        return $location;
    }

    /**
     * Get severity priority (higher is more severe)
     */
    public function getSeverityPriority(): int
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 5,
            self::SEVERITY_HIGH => 4,
            self::SEVERITY_MEDIUM => 3,
            self::SEVERITY_LOW => 2,
            self::SEVERITY_INFO => 1,
            default => 0
        };
    }

    /**
     * Check if issue is critical
     */
    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Check if issue is security-related
     */
    public function isSecurityIssue(): bool
    {
        return $this->category === self::CATEGORY_SECURITY;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'severity_priority' => $this->getSeverityPriority(),
            'category' => $this->category,
            'line' => $this->line,
            'column' => $this->column,
            'end_line' => $this->endLine,
            'end_column' => $this->endColumn,
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'tags' => $this->tags,
            'suggestions' => $this->suggestions,
            'code_snippet' => $this->codeSnippet,
            'metadata' => $this->metadata,
            'location_string' => $this->getLocationString(),
            'has_location' => $this->hasLocation(),
            'is_critical' => $this->isCritical(),
            'is_security_issue' => $this->isSecurityIssue(),
            'created_at' => $this->createdAt->format('c')
        ];
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'],
            severity: $data['severity'] ?? self::SEVERITY_MEDIUM,
            category: $data['category'] ?? self::CATEGORY_QUALITY,
            line: $data['line'] ?? null,
            column: $data['column'] ?? null,
            endLine: $data['end_line'] ?? null,
            endColumn: $data['end_column'] ?? null,
            ruleId: $data['rule_id'] ?? null,
            ruleName: $data['rule_name'] ?? null,
            tags: $data['tags'] ?? [],
            suggestions: $data['suggestions'] ?? [],
            codeSnippet: $data['code_snippet'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Get all valid severities
     */
    public static function getValidSeverities(): array
    {
        return [
            self::SEVERITY_CRITICAL,
            self::SEVERITY_HIGH,
            self::SEVERITY_MEDIUM,
            self::SEVERITY_LOW,
            self::SEVERITY_INFO
        ];
    }

    /**
     * Get all valid categories
     */
    public static function getValidCategories(): array
    {
        return [
            self::CATEGORY_SECURITY,
            self::CATEGORY_PERFORMANCE,
            self::CATEGORY_QUALITY,
            self::CATEGORY_SYNTAX,
            self::CATEGORY_STYLE,
            self::CATEGORY_MAINTAINABILITY,
            self::CATEGORY_COMPATIBILITY
        ];
    }

    /**
     * Validate severity value
     */
    private function validateSeverity(string $severity): void
    {
        if (!in_array($severity, self::getValidSeverities(), true)) {
            throw new \InvalidArgumentException("Invalid severity: {$severity}");
        }
    }

    /**
     * Validate category value
     */
    private function validateCategory(string $category): void
    {
        if (!in_array($category, self::getValidCategories(), true)) {
            throw new \InvalidArgumentException("Invalid category: {$category}");
        }
    }
}