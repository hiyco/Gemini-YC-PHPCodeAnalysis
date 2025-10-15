#!/bin/bash

# YC-PHP-Code-Analysis MCP Server Deployment Script
# Copyright: YC-2025Copyright
# Description: Production deployment automation with monitoring and rollback

set -euo pipefail

# Configuration
readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
readonly VERSION="${1:-latest}"
readonly ENVIRONMENT="${2:-production}"
readonly REGISTRY="${3:-ghcr.io/yc-2025/php-code-analysis-mcp-server}"

# Colors for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Deployment configuration
configure_deployment() {
    log_info "Configuring deployment for environment: $ENVIRONMENT"
    
    case "$ENVIRONMENT" in
        "development")
            REPLICAS=1
            RESOURCES_REQUESTS_CPU="100m"
            RESOURCES_REQUESTS_MEMORY="256Mi"
            RESOURCES_LIMITS_CPU="500m"
            RESOURCES_LIMITS_MEMORY="512Mi"
            ;;
        "staging")
            REPLICAS=2
            RESOURCES_REQUESTS_CPU="250m"
            RESOURCES_REQUESTS_MEMORY="512Mi"
            RESOURCES_LIMITS_CPU="1"
            RESOURCES_LIMITS_MEMORY="1Gi"
            ;;
        "production")
            REPLICAS=3
            RESOURCES_REQUESTS_CPU="250m"
            RESOURCES_REQUESTS_MEMORY="512Mi"
            RESOURCES_LIMITS_CPU="500m"
            RESOURCES_LIMITS_MEMORY="1Gi"
            ;;
        *)
            log_error "Unknown environment: $ENVIRONMENT"
            ;;
    esac
    
    log_success "Deployment configured for $ENVIRONMENT environment"
}

# Prerequisites check
check_prerequisites() {
    log_info "Checking deployment prerequisites..."
    
    # Check required tools
    local required_tools=("kubectl" "helm" "docker" "jq")
    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            log_error "$tool is not installed or not in PATH"
        fi
    done
    
    # Check kubectl connection
    if ! kubectl cluster-info &> /dev/null; then
        log_error "Cannot connect to Kubernetes cluster"
    fi
    
    # Check Docker daemon
    if ! docker info &> /dev/null; then
        log_error "Docker daemon is not running"
    fi
    
    log_success "Prerequisites check passed"
}

# Build and push Docker image
build_and_push_image() {
    log_info "Building and pushing Docker image..."
    
    cd "$PROJECT_ROOT"
    
    # Build multi-platform image
    docker buildx build \
        --platform linux/amd64,linux/arm64 \
        --tag "$REGISTRY:$VERSION" \
        --tag "$REGISTRY:latest" \
        --push \
        .
    
    # Verify image
    docker manifest inspect "$REGISTRY:$VERSION" > /dev/null
    
    log_success "Docker image built and pushed: $REGISTRY:$VERSION"
}

# Deploy to Kubernetes
deploy_to_kubernetes() {
    log_info "Deploying to Kubernetes..."
    
    # Create namespace if it doesn't exist
    kubectl create namespace yc-php-analysis --dry-run=client -o yaml | kubectl apply -f -
    
    # Generate deployment manifests
    envsubst < "$PROJECT_ROOT/k8s/deployment.yaml" > "/tmp/deployment-$ENVIRONMENT.yaml"
    
    # Apply manifests
    kubectl apply -f "/tmp/deployment-$ENVIRONMENT.yaml"
    
    log_success "Kubernetes deployment applied"
}

# Wait for deployment to be ready
wait_for_deployment() {
    log_info "Waiting for deployment to be ready..."
    
    kubectl wait --for=condition=available \
        --timeout=600s \
        deployment/yc-php-analysis-mcp \
        -n yc-php-analysis
    
    log_success "Deployment is ready"
}

# Run health checks
run_health_checks() {
    log_info "Running health checks..."
    
    # Get service endpoint
    local service_ip
    service_ip=$(kubectl get service yc-php-analysis-loadbalancer \
        -n yc-php-analysis \
        -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "")
    
    if [[ -z "$service_ip" ]]; then
        # Use port-forward for testing
        kubectl port-forward service/yc-php-analysis-service 3000:3000 -n yc-php-analysis &
        local port_forward_pid=$!
        sleep 5
        service_ip="localhost"
    fi
    
    # Health check
    local health_url="http://$service_ip:3000/health"
    local retry_count=0
    local max_retries=30
    
    while [[ $retry_count -lt $max_retries ]]; do
        if curl -f "$health_url" &> /dev/null; then
            log_success "Health check passed"
            break
        fi
        
        ((retry_count++))
        log_info "Health check attempt $retry_count/$max_retries..."
        sleep 10
    done
    
    if [[ $retry_count -eq $max_retries ]]; then
        log_error "Health check failed after $max_retries attempts"
    fi
    
    # Cleanup port-forward if used
    if [[ -n "${port_forward_pid:-}" ]]; then
        kill $port_forward_pid 2>/dev/null || true
    fi
}

# Performance validation
run_performance_tests() {
    log_info "Running performance validation..."
    
    # Basic load test
    local service_url
    service_url=$(kubectl get service yc-php-analysis-loadbalancer \
        -n yc-php-analysis \
        -o jsonpath='{.status.loadBalancer.ingress[0].ip}:80' 2>/dev/null || echo "localhost:3000")
    
    # Simple performance test
    if command -v ab &> /dev/null; then
        ab -n 100 -c 10 "http://$service_url/health" > "/tmp/perf-test-$ENVIRONMENT.txt"
        log_success "Performance test completed. Results saved to /tmp/perf-test-$ENVIRONMENT.txt"
    else
        log_warning "Apache Bench (ab) not available. Skipping performance test."
    fi
}

# Setup monitoring
setup_monitoring() {
    log_info "Setting up monitoring..."
    
    # Deploy monitoring stack if not exists
    if ! helm list -n monitoring | grep -q prometheus; then
        helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
        helm repo update
        
        helm install prometheus prometheus-community/kube-prometheus-stack \
            --namespace monitoring \
            --create-namespace \
            --values "$PROJECT_ROOT/monitoring/prometheus-values.yaml"
    fi
    
    # Apply service monitor
    kubectl apply -f "$PROJECT_ROOT/monitoring/service-monitor.yaml"
    
    log_success "Monitoring setup completed"
}

# Backup current deployment
backup_deployment() {
    log_info "Creating deployment backup..."
    
    local backup_dir="/tmp/yc-php-analysis-backup-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$backup_dir"
    
    # Backup current deployment
    kubectl get all -n yc-php-analysis -o yaml > "$backup_dir/current-deployment.yaml"
    
    # Backup configmaps and secrets
    kubectl get configmap -n yc-php-analysis -o yaml > "$backup_dir/configmaps.yaml"
    kubectl get secret -n yc-php-analysis -o yaml > "$backup_dir/secrets.yaml"
    
    echo "$backup_dir" > "/tmp/last-backup-location.txt"
    
    log_success "Backup created at: $backup_dir"
}

# Rollback function
rollback_deployment() {
    log_warning "Rolling back deployment..."
    
    if [[ -f "/tmp/last-backup-location.txt" ]]; then
        local backup_dir
        backup_dir=$(cat "/tmp/last-backup-location.txt")
        
        if [[ -d "$backup_dir" ]]; then
            kubectl apply -f "$backup_dir/current-deployment.yaml"
            log_success "Rollback completed using backup: $backup_dir"
        else
            log_error "Backup directory not found: $backup_dir"
        fi
    else
        # Use kubectl rollout undo as fallback
        kubectl rollout undo deployment/yc-php-analysis-mcp -n yc-php-analysis
        log_success "Rollback completed using kubectl undo"
    fi
}

# Cleanup function
cleanup_deployment() {
    log_info "Cleaning up old deployments..."
    
    # Keep only last 3 replica sets
    kubectl delete replicaset -n yc-php-analysis \
        --field-selector='status.replicas=0' \
        --sort-by='.metadata.creationTimestamp' \
        2>/dev/null || true
    
    # Clean up old Docker images
    docker image prune -a -f --filter "until=168h" || true
    
    log_success "Cleanup completed"
}

# Generate deployment report
generate_report() {
    log_info "Generating deployment report..."
    
    local report_file="/tmp/deployment-report-$(date +%Y%m%d-%H%M%S).json"
    
    cat > "$report_file" << EOF
{
  "deployment": {
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "version": "$VERSION",
    "environment": "$ENVIRONMENT",
    "image": "$REGISTRY:$VERSION",
    "replicas": $REPLICAS
  },
  "status": {
    "pods": $(kubectl get pods -n yc-php-analysis -o json | jq '.items | length'),
    "ready_pods": $(kubectl get pods -n yc-php-analysis -o json | jq '[.items[] | select(.status.phase == "Running")] | length'),
    "services": $(kubectl get services -n yc-php-analysis -o json | jq '.items | length')
  },
  "resources": {
    "cpu_requests": "$RESOURCES_REQUESTS_CPU",
    "memory_requests": "$RESOURCES_REQUESTS_MEMORY",
    "cpu_limits": "$RESOURCES_LIMITS_CPU",
    "memory_limits": "$RESOURCES_LIMITS_MEMORY"
  }
}
EOF
    
    log_success "Deployment report saved to: $report_file"
    cat "$report_file"
}

# Send notifications
send_notifications() {
    local status="$1"
    local message="$2"
    
    # Slack notification (if webhook URL is configured)
    if [[ -n "${SLACK_WEBHOOK_URL:-}" ]]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"🚀 YC-PHP-Analysis Deployment $status: $message\"}" \
            "$SLACK_WEBHOOK_URL" &> /dev/null || true
    fi
    
    # Discord notification (if webhook URL is configured)
    if [[ -n "${DISCORD_WEBHOOK_URL:-}" ]]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"content\":\"🚀 YC-PHP-Analysis Deployment $status: $message\"}" \
            "$DISCORD_WEBHOOK_URL" &> /dev/null || true
    fi
}

# Error handler
error_handler() {
    local line_number=$1
    log_error "Deployment failed at line $line_number"
    
    send_notifications "FAILED" "Deployment failed at line $line_number in $ENVIRONMENT environment"
    
    # Offer rollback option
    echo -n "Do you want to rollback? (y/N): "
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        rollback_deployment
    fi
    
    exit 1
}

# Set error trap
trap 'error_handler $LINENO' ERR

# Main deployment function
main() {
    log_info "Starting deployment of YC-PHP-Code-Analysis MCP Server"
    log_info "Version: $VERSION | Environment: $ENVIRONMENT"
    
    # Configuration
    configure_deployment
    
    # Pre-deployment checks
    check_prerequisites
    backup_deployment
    
    # Build and deploy
    build_and_push_image
    deploy_to_kubernetes
    
    # Post-deployment validation
    wait_for_deployment
    run_health_checks
    run_performance_tests
    
    # Setup monitoring
    setup_monitoring
    
    # Cleanup and reporting
    cleanup_deployment
    generate_report
    
    send_notifications "SUCCESS" "Successfully deployed version $VERSION to $ENVIRONMENT"
    
    log_success "🎉 Deployment completed successfully!"
    log_info "Service URL: http://$(kubectl get service yc-php-analysis-loadbalancer -n yc-php-analysis -o jsonpath='{.status.loadBalancer.ingress[0].ip}')/"
}

# Script usage
usage() {
    cat << EOF
Usage: $0 [VERSION] [ENVIRONMENT] [REGISTRY]

Arguments:
  VERSION     - Docker image version (default: latest)
  ENVIRONMENT - Deployment environment: development|staging|production (default: production)
  REGISTRY    - Docker registry URL (default: ghcr.io/yc-2025/php-code-analysis-mcp-server)

Environment Variables:
  SLACK_WEBHOOK_URL   - Slack notification webhook URL
  DISCORD_WEBHOOK_URL - Discord notification webhook URL

Examples:
  $0                                    # Deploy latest to production
  $0 v1.2.3 staging                   # Deploy specific version to staging
  $0 latest development my-registry.com/app  # Deploy to development with custom registry

EOF
}

# Handle script arguments
case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
    *)
        main "$@"
        ;;
esac