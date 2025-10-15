import * as vscode from 'vscode';

export class YcPcaHoverProvider implements vscode.HoverProvider {
    private securityPatterns: SecurityPattern[] = [
        {
            pattern: /\$_(GET|POST|REQUEST|COOKIE)\s*\[\s*['"][^'"]*['"]\s*\]/g,
            title: 'Potential Security Risk: User Input',
            description: 'Direct use of user input can lead to security vulnerabilities.',
            severity: 'high',
            owaspCategory: 'A03:2021 – Injection',
            recommendations: [
                'Always validate and sanitize user input',
                'Use prepared statements for database queries',
                'Apply proper escaping for output contexts',
                'Consider using input validation libraries'
            ],
            cweIds: [79, 89, 352]
        },
        {
            pattern: /(mysql_query|mysqli_query)\s*\(\s*['"][^'"]*\$[^'"]*['"]/g,
            title: 'SQL Injection Vulnerability',
            description: 'Direct string interpolation in SQL queries can lead to SQL injection attacks.',
            severity: 'critical',
            owaspCategory: 'A03:2021 – Injection',
            recommendations: [
                'Use prepared statements with parameter binding',
                'Validate input data types and ranges',
                'Use stored procedures where appropriate',
                'Apply least privilege principle for database access'
            ],
            cweIds: [89]
        },
        {
            pattern: /echo\s+\$_(GET|POST|REQUEST|COOKIE)/g,
            title: 'Cross-Site Scripting (XSS) Risk',
            description: 'Outputting user input without proper escaping can lead to XSS vulnerabilities.',
            severity: 'high',
            owaspCategory: 'A03:2021 – Injection',
            recommendations: [
                'Use htmlspecialchars() or htmlentities() for output escaping',
                'Implement Content Security Policy (CSP)',
                'Use template engines with automatic escaping',
                'Validate input on both client and server side'
            ],
            cweIds: [79]
        },
        {
            pattern: /(md5|sha1)\s*\(\s*\$\w+\s*\)/g,
            title: 'Weak Cryptographic Hash',
            description: 'MD5 and SHA1 are cryptographically weak and should not be used for password hashing.',
            severity: 'medium',
            owaspCategory: 'A02:2021 – Cryptographic Failures',
            recommendations: [
                'Use password_hash() with PASSWORD_DEFAULT',
                'Consider bcrypt, scrypt, or Argon2 for password hashing',
                'Use salt values to prevent rainbow table attacks',
                'Regularly update hashing algorithms'
            ],
            cweIds: [327, 328]
        },
        {
            pattern: /eval\s*\(\s*\$[^)]+\)/g,
            title: 'Code Injection Risk',
            description: 'Using eval() with user-controlled input can lead to code injection vulnerabilities.',
            severity: 'critical',
            owaspCategory: 'A03:2021 – Injection',
            recommendations: [
                'Avoid using eval() entirely when possible',
                'Use specific parsing functions instead',
                'If eval() is necessary, strictly validate input',
                'Consider safer alternatives like JSON parsing'
            ],
            cweIds: [94, 95]
        },
        {
            pattern: /(exec|shell_exec|system|passthru)\s*\(\s*[^)]*\$[^)]*\)/g,
            title: 'Command Injection Risk',
            description: 'Executing system commands with user input can lead to command injection attacks.',
            severity: 'critical',
            owaspCategory: 'A03:2021 – Injection',
            recommendations: [
                'Use escapeshellarg() and escapeshellcmd() for user input',
                'Validate input against allowlists',
                'Use specific PHP functions instead of shell commands',
                'Run with minimal privileges'
            ],
            cweIds: [78]
        },
        {
            pattern: /file_get_contents\s*\(\s*\$_(GET|POST|REQUEST)\s*\[/g,
            title: 'Path Traversal Risk',
            description: 'Using user input in file operations can lead to path traversal attacks.',
            severity: 'high',
            owaspCategory: 'A01:2021 – Broken Access Control',
            recommendations: [
                'Validate file paths against allowlists',
                'Use realpath() to resolve path traversal attempts',
                'Implement proper access controls',
                'Consider using file operation wrappers'
            ],
            cweIds: [22]
        },
        {
            pattern: /(curl_setopt.*CURLOPT_SSL_VERIFYPEER.*false|curl_setopt.*CURLOPT_SSL_VERIFYHOST.*false)/g,
            title: 'Insecure SSL/TLS Configuration',
            description: 'Disabling SSL/TLS verification makes connections vulnerable to man-in-the-middle attacks.',
            severity: 'high',
            owaspCategory: 'A05:2021 – Security Misconfiguration',
            recommendations: [
                'Always verify SSL certificates in production',
                'Use proper certificate stores',
                'Implement certificate pinning for critical connections',
                'Use CURLOPT_CAINFO for custom CA certificates'
            ],
            cweIds: [295]
        },
        {
            pattern: /session_start\s*\(\s*\)\s*;.*\$_SESSION\s*\[\s*['"]admin['"]\s*\]\s*=\s*true/gm,
            title: 'Weak Session Management',
            description: 'Simple boolean flags for authentication can be easily manipulated.',
            severity: 'medium',
            owaspCategory: 'A07:2021 – Identification and Authentication Failures',
            recommendations: [
                'Use secure session management practices',
                'Implement proper user authentication mechanisms',
                'Use session tokens with sufficient entropy',
                'Implement session timeout and regeneration'
            ],
            cweIds: [287, 384]
        },
        {
            pattern: /\$password\s*=\s*['"][^'"]{1,8}['"]/g,
            title: 'Weak Password Policy',
            description: 'Hardcoded or weak passwords pose security risks.',
            severity: 'medium',
            owaspCategory: 'A07:2021 – Identification and Authentication Failures',
            recommendations: [
                'Enforce strong password policies',
                'Never hardcode passwords in source code',
                'Use environment variables for credentials',
                'Implement multi-factor authentication'
            ],
            cweIds: [259, 521]
        }
    ];

    public provideHover(
        document: vscode.TextDocument,
        position: vscode.Position,
        _token: vscode.CancellationToken
    ): vscode.ProviderResult<vscode.Hover> {
        const line = document.lineAt(position);
        const lineText = line.text;

        for (const pattern of this.securityPatterns) {
            const matches = [...lineText.matchAll(pattern.pattern)];
            
            for (const match of matches) {
                if (match.index !== undefined) {
                    const startPos = match.index;
                    const endPos = startPos + match[0].length;
                    
                    if (position.character >= startPos && position.character <= endPos) {
                        return this.createSecurityHover(pattern, match[0], position);
                    }
                }
            }
        }

        // Check for performance patterns
        return this.checkPerformancePatterns(document, position, lineText);
    }

    private createSecurityHover(pattern: SecurityPattern, matchedText: string, position: vscode.Position): vscode.Hover {
        const severityIcon = this.getSeverityIcon(pattern.severity);
        const owaspIcon = '🛡️';
        
        const markdownString = new vscode.MarkdownString();
        markdownString.isTrusted = true;
        
        // Title with severity
        markdownString.appendMarkdown(`### ${severityIcon} ${pattern.title}\n\n`);
        
        // OWASP Category
        markdownString.appendMarkdown(`**${owaspIcon} OWASP Category:** ${pattern.owaspCategory}\n\n`);
        
        // Description
        markdownString.appendMarkdown(`**Description:**  \n${pattern.description}\n\n`);
        
        // Code context
        markdownString.appendMarkdown(`**Vulnerable Code:**  \n\`\`\`php\n${matchedText}\n\`\`\`\n\n`);
        
        // CWE Information
        if (pattern.cweIds && pattern.cweIds.length > 0) {
            const cweLinks = pattern.cweIds.map(id => 
                `[CWE-${id}](https://cwe.mitre.org/data/definitions/${id}.html)`
            ).join(', ');
            markdownString.appendMarkdown(`**Related CWEs:** ${cweLinks}\n\n`);
        }
        
        // Recommendations
        markdownString.appendMarkdown(`**Recommendations:**\n`);
        pattern.recommendations.forEach(rec => {
            markdownString.appendMarkdown(`- ${rec}\n`);
        });
        
        // Action links
        markdownString.appendMarkdown(`\n---\n`);
        markdownString.appendMarkdown(`[View Security Panel](command:yc-pca.showSecurityPanel) | `);
        markdownString.appendMarkdown(`[Analyze File](command:yc-pca.analyzeFile) | `);
        markdownString.appendMarkdown(`[Generate Report](command:yc-pca.generateReport)`);
        
        const range = new vscode.Range(position, position);
        return new vscode.Hover(markdownString, range);
    }

    private checkPerformancePatterns(
        _document: vscode.TextDocument,
        position: vscode.Position,
        lineText: string
    ): vscode.ProviderResult<vscode.Hover> {
        const performancePatterns = [
            {
                pattern: /for\s*\([^)]*\)\s*\{[^}]*for\s*\([^)]*\)/g,
                title: 'Nested Loop Performance Warning',
                description: 'Nested loops can result in O(n²) or higher complexity, impacting performance.',
                impact: 'high',
                recommendations: [
                    'Consider using more efficient algorithms',
                    'Use array_map() or array_filter() for simple operations',
                    'Break early when possible',
                    'Consider data structure optimizations'
                ]
            },
            {
                pattern: /mysql_query\s*\(\s*['"][^'"]*['"][^)]*\)\s*;[^}]*mysql_query/gm,
                title: 'N+1 Query Problem',
                description: 'Multiple database queries in a loop can cause significant performance issues.',
                impact: 'critical',
                recommendations: [
                    'Use JOIN queries to fetch related data',
                    'Implement query batching',
                    'Use database query optimization techniques',
                    'Consider ORM with eager loading'
                ]
            }
        ];

        for (const pattern of performancePatterns) {
            const matches = [...lineText.matchAll(pattern.pattern)];
            
            for (const match of matches) {
                if (match.index !== undefined) {
                    const startPos = match.index;
                    const endPos = startPos + match[0].length;
                    
                    if (position.character >= startPos && position.character <= endPos) {
                        return this.createPerformanceHover(pattern, match[0], position);
                    }
                }
            }
        }

        return null;
    }

    private createPerformanceHover(pattern: any, matchedText: string, position: vscode.Position): vscode.Hover {
        const impactIcon = this.getImpactIcon(pattern.impact);
        
        const markdownString = new vscode.MarkdownString();
        markdownString.isTrusted = true;
        
        markdownString.appendMarkdown(`### ⚡ ${impactIcon} ${pattern.title}\n\n`);
        markdownString.appendMarkdown(`**Performance Impact:** ${pattern.impact.toUpperCase()}\n\n`);
        markdownString.appendMarkdown(`**Description:**  \n${pattern.description}\n\n`);
        markdownString.appendMarkdown(`**Code Pattern:**  \n\`\`\`php\n${matchedText}\n\`\`\`\n\n`);
        
        markdownString.appendMarkdown(`**Optimization Recommendations:**\n`);
        pattern.recommendations.forEach((rec: string) => {
            markdownString.appendMarkdown(`- ${rec}\n`);
        });
        
        markdownString.appendMarkdown(`\n[View Performance Panel](command:yc-pca-performance.focus) | `);
        markdownString.appendMarkdown(`[Run Benchmarks](command:yc-pca.runBenchmarks)`);
        
        const range = new vscode.Range(position, position);
        return new vscode.Hover(markdownString, range);
    }

    private getSeverityIcon(severity: string): string {
        switch (severity.toLowerCase()) {
            case 'critical':
                return '🚨';
            case 'high':
                return '⚠️';
            case 'medium':
                return '📋';
            case 'low':
                return '💡';
            default:
                return 'ℹ️';
        }
    }

    private getImpactIcon(impact: string): string {
        switch (impact.toLowerCase()) {
            case 'critical':
                return '🔥';
            case 'high':
                return '🎯';
            case 'medium':
                return '⏱️';
            case 'low':
                return '💡';
            default:
                return '📊';
        }
    }
}

interface SecurityPattern {
    pattern: RegExp;
    title: string;
    description: string;
    severity: 'critical' | 'high' | 'medium' | 'low';
    owaspCategory: string;
    recommendations: string[];
    cweIds: number[];
}