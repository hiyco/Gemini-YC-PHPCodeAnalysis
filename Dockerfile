# Copyright: YC-2025Copyright
# Created: 2025-09-03
# Author: Claude Code
# Description: Docker configuration for PHP Analysis MCP Server

# Multi-stage build for optimized production image
FROM node:18-alpine AS builder

# Set working directory
WORKDIR /app

# Install system dependencies for native modules
RUN apk add --no-cache python3 make g++ git

# Copy package files
COPY package*.json ./

# Install all dependencies (including dev dependencies for build)
RUN npm ci --include=dev

# Copy source code
COPY . .

# Build the application
RUN npm run build

# Remove dev dependencies and clean npm cache
RUN npm ci --only=production && npm cache clean --force

# Production stage
FROM node:18-alpine AS production

# Create app user for security
RUN addgroup -g 1001 -S nodejs && \
    adduser -S mcpserver -u 1001

# Install runtime dependencies
RUN apk add --no-cache \
    dumb-init \
    tini \
    curl \
    ca-certificates

# Set working directory
WORKDIR /app

# Copy built application from builder stage
COPY --from=builder --chown=mcpserver:nodejs /app/dist ./dist
COPY --from=builder --chown=mcpserver:nodejs /app/node_modules ./node_modules
COPY --from=builder --chown=mcpserver:nodejs /app/package*.json ./

# Create necessary directories
RUN mkdir -p /app/logs /app/data /app/plugins && \
    chown -R mcpserver:nodejs /app

# Switch to non-root user
USER mcpserver

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:3000/health || exit 1

# Expose port
EXPOSE 3000

# Set environment variables
ENV NODE_ENV=production
ENV MCP_PORT=3000
ENV MCP_HOST=0.0.0.0
ENV LOG_LEVEL=info

# Use tini for proper signal handling
ENTRYPOINT ["/sbin/tini", "--"]

# Start the application
CMD ["node", "dist/server.js"]

# Labels for metadata
LABEL maintainer="YC-2025Copyright" \
      version="1.0.0" \
      description="PHP Analysis MCP Server" \
      org.opencontainers.image.title="YC PHP Analysis MCP Server" \
      org.opencontainers.image.description="Professional MCP Server for PHP Code Analysis with AI Integration" \
      org.opencontainers.image.version="1.0.0" \
      org.opencontainers.image.created="2025-09-03" \
      org.opencontainers.image.source="https://github.com/yc-2025/php-code-analysis-mcp-server" \
      org.opencontainers.image.licenses="MIT"