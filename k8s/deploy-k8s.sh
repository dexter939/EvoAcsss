#!/bin/bash
set -e

# ACS Kubernetes Deployment Script
# Carrier-grade deployment for 100K+ CPE devices

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NAMESPACE="${NAMESPACE:-acs-production}"
RELEASE_NAME="${RELEASE_NAME:-acs}"
HELM_TIMEOUT="${HELM_TIMEOUT:-10m}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check kubectl
    if ! command -v kubectl &> /dev/null; then
        log_error "kubectl is not installed. Please install kubectl first."
        exit 1
    fi
    
    # Check helm
    if ! command -v helm &> /dev/null; then
        log_error "helm is not installed. Please install Helm 3+ first."
        exit 1
    fi
    
    # Check cluster connectivity
    if ! kubectl cluster-info &> /dev/null; then
        log_error "Cannot connect to Kubernetes cluster. Check your kubeconfig."
        exit 1
    fi
    
    log_success "All prerequisites met"
}

# Create namespace
create_namespace() {
    log_info "Creating namespace: $NAMESPACE"
    
    if kubectl get namespace "$NAMESPACE" &> /dev/null; then
        log_warning "Namespace $NAMESPACE already exists"
    else
        kubectl create namespace "$NAMESPACE"
        log_success "Namespace created: $NAMESPACE"
    fi
}

# Create secrets
create_secrets() {
    log_info "Creating secrets..."
    
    # Prompt for required secrets
    read -sp "Enter PostgreSQL password: " POSTGRES_PASSWORD
    echo
    read -sp "Enter Redis password: " REDIS_PASSWORD
    echo
    read -sp "Enter Laravel APP_KEY (leave empty to generate): " APP_KEY
    echo
    read -sp "Enter OpenAI API key (optional): " OPENAI_API_KEY
    echo
    
    # Generate APP_KEY if not provided
    if [ -z "$APP_KEY" ]; then
        log_info "Generating Laravel APP_KEY..."
        APP_KEY=$(openssl rand -base64 32)
        log_success "APP_KEY generated"
    fi
    
    # Create acs-secrets
    kubectl create secret generic acs-secrets \
        --from-literal=postgres-password="$POSTGRES_PASSWORD" \
        --from-literal=postgres-user-password="$POSTGRES_PASSWORD" \
        --from-literal=redis-password="$REDIS_PASSWORD" \
        --namespace="$NAMESPACE" \
        --dry-run=client -o yaml | kubectl apply -f -
    
    log_success "Secrets created"
}

# Deploy with Helm
deploy_helm() {
    log_info "Deploying ACS with Helm..."
    
    cd "$SCRIPT_DIR/helm/acs"
    
    helm upgrade --install "$RELEASE_NAME" . \
        --namespace "$NAMESPACE" \
        --create-namespace \
        --timeout "$HELM_TIMEOUT" \
        --wait \
        --atomic \
        --values values.yaml \
        "$@"
    
    log_success "Helm deployment completed"
}

# Verify deployment
verify_deployment() {
    log_info "Verifying deployment..."
    
    # Check pods
    log_info "Checking pods..."
    kubectl get pods -n "$NAMESPACE"
    
    # Wait for pods to be ready
    log_info "Waiting for pods to be ready..."
    kubectl wait --for=condition=ready pod \
        -l app.kubernetes.io/instance="$RELEASE_NAME" \
        -n "$NAMESPACE" \
        --timeout=5m || true
    
    # Check services
    log_info "Checking services..."
    kubectl get services -n "$NAMESPACE"
    
    # Check ingress
    log_info "Checking ingress..."
    kubectl get ingress -n "$NAMESPACE"
    
    log_success "Deployment verification completed"
}

# Get deployment info
get_info() {
    log_info "Deployment Information:"
    echo ""
    echo "Namespace: $NAMESPACE"
    echo "Release: $RELEASE_NAME"
    echo ""
    
    # Get LoadBalancer IP
    log_info "TR-069 LoadBalancer:"
    kubectl get svc "${RELEASE_NAME}-acs-tr069" -n "$NAMESPACE" -o wide
    
    # Get Ingress
    log_info "Web Interface:"
    kubectl get ingress -n "$NAMESPACE"
    
    # Get pods status
    echo ""
    log_info "Pods Status:"
    kubectl get pods -n "$NAMESPACE" -o wide
}

# Show logs
show_logs() {
    local component="${1:-app}"
    
    log_info "Showing logs for component: $component"
    
    case $component in
        app)
            kubectl logs -l app.kubernetes.io/component=app -n "$NAMESPACE" --tail=100 -f
            ;;
        worker)
            kubectl logs -l app.kubernetes.io/component=worker -n "$NAMESPACE" --tail=100 -f
            ;;
        postgres)
            kubectl logs -l app.kubernetes.io/component=database -n "$NAMESPACE" --tail=100 -f
            ;;
        redis)
            kubectl logs -l app.kubernetes.io/component=cache -n "$NAMESPACE" --tail=100 -f
            ;;
        *)
            log_error "Unknown component: $component"
            echo "Available components: app, worker, postgres, redis"
            exit 1
            ;;
    esac
}

# Scale deployment
scale_deployment() {
    local component="$1"
    local replicas="$2"
    
    if [ -z "$component" ] || [ -z "$replicas" ]; then
        log_error "Usage: $0 scale <component> <replicas>"
        echo "Components: app, worker"
        exit 1
    fi
    
    case $component in
        app)
            kubectl scale deployment "${RELEASE_NAME}-acs-app" -n "$NAMESPACE" --replicas="$replicas"
            ;;
        worker)
            kubectl scale deployment "${RELEASE_NAME}-acs-worker" -n "$NAMESPACE" --replicas="$replicas"
            ;;
        *)
            log_error "Unknown component: $component"
            exit 1
            ;;
    esac
    
    log_success "Scaled $component to $replicas replicas"
}

# Rollback deployment
rollback_deployment() {
    log_warning "Rolling back deployment..."
    
    helm rollback "$RELEASE_NAME" -n "$NAMESPACE"
    
    log_success "Rollback completed"
}

# Delete deployment
delete_deployment() {
    log_warning "This will delete the entire ACS deployment!"
    read -p "Are you sure? (yes/no): " confirm
    
    if [ "$confirm" != "yes" ]; then
        log_info "Deletion cancelled"
        exit 0
    fi
    
    log_info "Deleting Helm release..."
    helm uninstall "$RELEASE_NAME" -n "$NAMESPACE"
    
    log_info "Deleting namespace..."
    kubectl delete namespace "$NAMESPACE"
    
    log_success "Deployment deleted"
}

# Main menu
show_usage() {
    cat << EOF
ACS Kubernetes Deployment Script

Usage: $0 <command> [options]

Commands:
    install         Install ACS on Kubernetes
    upgrade         Upgrade existing deployment
    info            Show deployment information
    logs <comp>     Show logs (app|worker|postgres|redis)
    scale <c> <n>   Scale component to N replicas
    rollback        Rollback to previous version
    delete          Delete deployment
    help            Show this help message

Environment Variables:
    NAMESPACE       Kubernetes namespace (default: acs-production)
    RELEASE_NAME    Helm release name (default: acs)
    HELM_TIMEOUT    Deployment timeout (default: 10m)

Examples:
    # Fresh installation
    $0 install

    # Upgrade with custom values
    $0 upgrade --set replicaCount.app=5

    # Show app logs
    $0 logs app

    # Scale workers to 10
    $0 scale worker 10

    # Get deployment info
    $0 info

EOF
}

# Main script
main() {
    case "${1:-help}" in
        install)
            check_prerequisites
            create_namespace
            create_secrets
            shift
            deploy_helm "$@"
            verify_deployment
            get_info
            ;;
        upgrade)
            check_prerequisites
            shift
            deploy_helm "$@"
            verify_deployment
            ;;
        info)
            get_info
            ;;
        logs)
            show_logs "$2"
            ;;
        scale)
            scale_deployment "$2" "$3"
            ;;
        rollback)
            rollback_deployment
            ;;
        delete)
            delete_deployment
            ;;
        help|--help|-h)
            show_usage
            ;;
        *)
            log_error "Unknown command: $1"
            show_usage
            exit 1
            ;;
    esac
}

main "$@"
