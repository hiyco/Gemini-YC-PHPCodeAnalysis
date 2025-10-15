#!/usr/bin/env node

/**
 * Multi-platform Build Script
 * Copyright: YC-2025Copyright
 * Description: Build and package YC-PHP-Code-Analysis for multiple platforms
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Platform configurations
const PLATFORMS = {
  'linux-x64': {
    target: 'node18-linux-x64',
    archive: 'tar.gz',
    executable: 'yc-php-analysis-mcp'
  },
  'linux-arm64': {
    target: 'node18-linux-arm64', 
    archive: 'tar.gz',
    executable: 'yc-php-analysis-mcp'
  },
  'win-x64': {
    target: 'node18-win-x64',
    archive: 'zip',
    executable: 'yc-php-analysis-mcp.exe'
  },
  'win-arm64': {
    target: 'node18-win-arm64',
    archive: 'zip', 
    executable: 'yc-php-analysis-mcp.exe'
  },
  'macos-x64': {
    target: 'node18-macos-x64',
    archive: 'tar.gz',
    executable: 'yc-php-analysis-mcp'
  },
  'macos-arm64': {
    target: 'node18-macos-arm64',
    archive: 'tar.gz',
    executable: 'yc-php-analysis-mcp'
  }
};

class PlatformBuilder {
  constructor() {
    this.projectRoot = path.resolve(__dirname, '..');
    this.distDir = path.join(this.projectRoot, 'dist-platform');
    this.packageJson = require('../package.json');
  }

  async build() {
    console.log('🚀 Starting multi-platform build process...');
    
    // Clean and create distribution directory
    this.cleanDistDirectory();
    
    // Build TypeScript source
    await this.buildTypeScript();
    
    // Create platform-specific packages
    for (const [platform, config] of Object.entries(PLATFORMS)) {
      try {
        await this.buildPlatform(platform, config);
        console.log(`✅ Successfully built for ${platform}`);
      } catch (error) {
        console.error(`❌ Failed to build for ${platform}:`, error.message);
      }
    }
    
    // Generate checksums
    await this.generateChecksums();
    
    console.log('🎉 Multi-platform build completed!');
    console.log(`📦 Packages available in: ${this.distDir}`);
  }

  cleanDistDirectory() {
    console.log('🧹 Cleaning distribution directory...');
    
    if (fs.existsSync(this.distDir)) {
      fs.rmSync(this.distDir, { recursive: true, force: true });
    }
    
    fs.mkdirSync(this.distDir, { recursive: true });
  }

  async buildTypeScript() {
    console.log('🔨 Building TypeScript source...');
    
    try {
      execSync('npm run build', { 
        cwd: this.projectRoot,
        stdio: 'inherit'
      });
    } catch (error) {
      throw new Error('TypeScript build failed');
    }
  }

  async buildPlatform(platform, config) {
    console.log(`📦 Building for ${platform}...`);
    
    const platformDir = path.join(this.distDir, platform);
    fs.mkdirSync(platformDir, { recursive: true });
    
    // Build executable using pkg
    const executable = path.join(platformDir, config.executable);
    const pkgCommand = `npx pkg package.json --targets ${config.target} --output "${executable}"`;
    
    try {
      execSync(pkgCommand, {
        cwd: this.projectRoot,
        stdio: 'pipe'
      });
    } catch (error) {
      throw new Error(`pkg build failed: ${error.message}`);
    }

    // Copy additional files
    this.copyAdditionalFiles(platformDir);
    
    // Create platform-specific configuration
    this.createPlatformConfig(platformDir, platform);
    
    // Create archive
    await this.createArchive(platform, config, platformDir);
  }

  copyAdditionalFiles(platformDir) {
    const filesToCopy = [
      'README.md',
      'LICENSE',
      'CHANGELOG.md'
    ];
    
    filesToCopy.forEach(file => {
      const srcPath = path.join(this.projectRoot, file);
      const destPath = path.join(platformDir, file);
      
      if (fs.existsSync(srcPath)) {
        fs.copyFileSync(srcPath, destPath);
      }
    });
    
    // Copy example configuration
    const exampleDir = path.join(platformDir, 'examples');
    fs.mkdirSync(exampleDir, { recursive: true });
    
    const exampleConfig = {
      server: {
        port: 3000,
        host: '0.0.0.0'
      },
      analysis: {
        types: ['syntax', 'security', 'performance'],
        cache: true
      },
      security: {
        enabled: true,
        jwt_secret: 'your-secret-here'
      }
    };
    
    fs.writeFileSync(
      path.join(exampleDir, 'config.json'),
      JSON.stringify(exampleConfig, null, 2)
    );
  }

  createPlatformConfig(platformDir, platform) {
    const isWindows = platform.includes('win');
    
    // Create startup scripts
    if (isWindows) {
      const batchScript = `@echo off
echo Starting YC PHP Code Analysis MCP Server...
yc-php-analysis-mcp.exe --config examples\\config.json
pause`;
      
      fs.writeFileSync(path.join(platformDir, 'start.bat'), batchScript);
    } else {
      const bashScript = `#!/bin/bash
echo "Starting YC PHP Code Analysis MCP Server..."
./yc-php-analysis-mcp --config examples/config.json`;
      
      fs.writeFileSync(path.join(platformDir, 'start.sh'), bashScript);
      
      // Make script executable
      try {
        execSync(`chmod +x "${path.join(platformDir, 'start.sh')}"`);
      } catch (error) {
        console.warn('Warning: Could not make start.sh executable');
      }
    }
    
    // Create systemd service file for Linux
    if (platform.includes('linux')) {
      const serviceFile = `[Unit]
Description=YC PHP Code Analysis MCP Server
After=network.target

[Service]
Type=simple
User=mcpserver
WorkingDirectory=/opt/yc-php-analysis-mcp
ExecStart=/opt/yc-php-analysis-mcp/yc-php-analysis-mcp --config /opt/yc-php-analysis-mcp/config.json
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target`;
      
      fs.writeFileSync(
        path.join(platformDir, 'yc-php-analysis-mcp.service'),
        serviceFile
      );
    }
  }

  async createArchive(platform, config, platformDir) {
    const archiveName = `yc-php-analysis-mcp-${this.packageJson.version}-${platform}`;
    const archivePath = path.join(this.distDir, `${archiveName}.${config.archive}`);
    
    try {
      if (config.archive === 'tar.gz') {
        execSync(
          `tar -czf "${archivePath}" -C "${this.distDir}" "${platform}"`,
          { stdio: 'pipe' }
        );
      } else if (config.archive === 'zip') {
        // Use cross-platform zip command or 7z if available
        try {
          execSync(
            `7z a "${archivePath}" "${platformDir}\\*"`,
            { stdio: 'pipe' }
          );
        } catch {
          // Fallback to zip command on Unix systems
          execSync(
            `cd "${this.distDir}" && zip -r "${archiveName}.zip" "${platform}"`,
            { stdio: 'pipe' }
          );
        }
      }
    } catch (error) {
      throw new Error(`Archive creation failed: ${error.message}`);
    }
  }

  async generateChecksums() {
    console.log('🔐 Generating checksums...');
    
    const checksums = [];
    const files = fs.readdirSync(this.distDir);
    
    for (const file of files) {
      if (file.endsWith('.tar.gz') || file.endsWith('.zip')) {
        const filePath = path.join(this.distDir, file);
        
        try {
          const hash = execSync(`shasum -a 256 "${filePath}"`, { encoding: 'utf8' });
          checksums.push(hash.trim());
        } catch (error) {
          console.warn(`Warning: Could not generate checksum for ${file}`);
        }
      }
    }
    
    if (checksums.length > 0) {
      fs.writeFileSync(
        path.join(this.distDir, 'SHA256SUMS'),
        checksums.join('\n') + '\n'
      );
    }
  }
}

// CLI interface
if (require.main === module) {
  const builder = new PlatformBuilder();
  
  builder.build().catch(error => {
    console.error('❌ Build failed:', error);
    process.exit(1);
  });
}

module.exports = PlatformBuilder;