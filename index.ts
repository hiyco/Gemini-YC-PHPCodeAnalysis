/**
 * @license
 * Copyright 2025 Google LLC
 * SPDX-License-Identifier: Apache-2.0
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { spawn } from 'child_process';
import { z } from 'zod';
import path from 'path';
import { fileURLToPath } from 'url';

const server = new McpServer({
  name: 'php-code-analysis-server',
  version: '1.0.0',
});

// Helper to get the directory name in ES modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

server.registerTool(
  'analyze_php_file',
  {
    description: 'Analyzes a PHP file for security vulnerabilities and quality issues.',
    inputSchema: z.object({
      filePath: z.string().describe('The absolute path to the PHP file to analyze.'),
    }).shape,
  },
  async ({ filePath }: { filePath: string }) => {
    return new Promise((resolve, reject) => {
      const phpScriptPath = path.join(__dirname, '..', 'php_analyzer', 'bin', 'console');
      const args = ['code:analyze', filePath, '--format=json'];
      
      const phpProcess = spawn('php', [phpScriptPath, ...args]);

      let stdout = '';
      let stderr = '';

      phpProcess.stdout.on('data', (data) => {
        stdout += data.toString();
      });

      phpProcess.stderr.on('data', (data) => {
        stderr += data.toString();
      });

      phpProcess.on('close', (code) => {
        if (code !== 0) {
          console.error(`PHP script exited with code ${code}`);
          console.error(`stderr: ${stderr}`);
          // Even if there's an error, some analysis might be in stdout
          // Or we might want to return the error itself.
          // For now, let's return a combination.
           resolve({
            content: [{
              type: 'text',
              text: `Error analyzing file. Exit code: ${code}\nStderr: ${stderr}\nStdout: ${stdout}`,
            }],
          });
        } else {
          resolve({
            content: [{
              type: 'text',
              text: stdout,
            }],
          });
        }
      });

      phpProcess.on('error', (err) => {
        console.error('Failed to start PHP script.', err);
        reject(err);
      });
    });
  }
);

const transport = new StdioServerTransport();
await server.connect(transport);
