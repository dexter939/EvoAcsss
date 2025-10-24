#!/bin/bash

# K6 Load Testing - Test Execution Script
# Provides convenient commands for running different test scenarios

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPORTS_DIR="tests/Load/reports"
SCENARIOS_DIR="tests/Load/scenarios"

# Ensure reports directory exists
mkdir -p "$REPORTS_DIR"

# Print header
print_header() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  ACS Load Testing - $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

# Check K6 installation
check_k6() {
    if ! command -v k6 &> /dev/null; then
        echo -e "${RED}❌ K6 is not installed${NC}"
        echo ""
        echo "Install K6 using:"
        echo "  ./tests/Load/install-k6.sh"
        echo ""
        echo "Or manually from: https://k6.io/docs/get-started/installation/"
        exit 1
    fi
}

# Smoke test - quick validation
run_smoke_test() {
    print_header "Smoke Test (Quick Validation)"
    echo "Running quick smoke test with minimal load..."
    echo ""
    
    k6 run --vus 10 --duration 30s \
        --out json="$REPORTS_DIR/smoke-test.json" \
        "$SCENARIOS_DIR/api-rest.js"
    
    echo ""
    echo -e "${GREEN}✅ Smoke test completed${NC}"
}

# REST API load test
run_api_test() {
    print_header "REST API Load Test"
    echo "Testing REST API endpoints with progressive load..."
    echo "Target: 1,000 concurrent users"
    echo ""
    
    k6 run \
        --out json="$REPORTS_DIR/api-rest-results.json" \
        "$SCENARIOS_DIR/api-rest.js"
    
    echo ""
    echo -e "${GREEN}✅ REST API load test completed${NC}"
    echo "Results: $REPORTS_DIR/api-rest-results.json"
}

# TR-069 protocol test
run_tr069_test() {
    print_header "TR-069 Protocol Load Test"
    echo "Testing TR-069 CWMP protocol..."
    echo "Target: 50,000 concurrent CPE devices"
    echo ""
    
    k6 run \
        --out json="$REPORTS_DIR/tr069-results.json" \
        "$SCENARIOS_DIR/tr069.js"
    
    echo ""
    echo -e "${GREEN}✅ TR-069 protocol load test completed${NC}"
    echo "Results: $REPORTS_DIR/tr069-results.json"
}

# TR-369 USP protocol test
run_tr369_test() {
    print_header "TR-369 USP Protocol Load Test"
    echo "Testing TR-369 USP protocol with multiple transports..."
    echo "Transport mix: 40% HTTP, 30% MQTT, 30% WebSocket"
    echo "Target: 30,000 concurrent USP sessions"
    echo ""
    
    k6 run \
        --out json="$REPORTS_DIR/tr369-results.json" \
        "$SCENARIOS_DIR/tr369.js"
    
    echo ""
    echo -e "${GREEN}✅ TR-369 USP protocol load test completed${NC}"
    echo "Results: $REPORTS_DIR/tr369-results.json"
}

# Mixed protocol test - production simulation
run_mixed_test() {
    print_header "Mixed Protocol Load Test (Production Simulation)"
    echo "Simulating real production environment..."
    echo "Protocol mix: 60% TR-069, 30% TR-369, 10% REST API"
    echo "Target: 100,000 concurrent devices"
    echo ""
    echo -e "${YELLOW}⚠️  This test will run for ~75 minutes${NC}"
    echo ""
    
    read -p "Continue? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Test cancelled."
        exit 0
    fi
    
    k6 run \
        --out json="$REPORTS_DIR/mixed-results.json" \
        "$SCENARIOS_DIR/mixed.js"
    
    echo ""
    echo -e "${GREEN}✅ Mixed protocol load test completed${NC}"
    echo "Results: $REPORTS_DIR/mixed-results.json"
}

# Custom test with parameters
run_custom_test() {
    print_header "Custom Load Test"
    
    if [ -z "$2" ]; then
        echo "Usage: $0 custom <scenario> [vus] [duration]"
        echo ""
        echo "Examples:"
        echo "  $0 custom api-rest 500 5m"
        echo "  $0 custom tr069 10000 10m"
        echo "  $0 custom mixed 50000 30m"
        exit 1
    fi
    
    local scenario=$2
    local vus=${3:-100}
    local duration=${4:-1m}
    
    echo "Scenario: $scenario"
    echo "Virtual Users: $vus"
    echo "Duration: $duration"
    echo ""
    
    k6 run --vus "$vus" --duration "$duration" \
        --out json="$REPORTS_DIR/custom-$scenario-results.json" \
        "$SCENARIOS_DIR/$scenario.js"
    
    echo ""
    echo -e "${GREEN}✅ Custom test completed${NC}"
}

# Soak test - prolonged stability test
run_soak_test() {
    print_header "Soak Test (24h Stability)"
    echo "Running prolonged stability test..."
    echo "Duration: 24 hours"
    echo "Load: 5,000 concurrent devices (sustained)"
    echo ""
    echo -e "${YELLOW}⚠️  This test will run for 24 hours${NC}"
    echo ""
    
    read -p "Continue? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Test cancelled."
        exit 0
    fi
    
    k6 run --vus 5000 --duration 24h \
        --out json="$REPORTS_DIR/soak-test-results.json" \
        "$SCENARIOS_DIR/mixed.js"
    
    echo ""
    echo -e "${GREEN}✅ Soak test completed${NC}"
}

# Spike test - sudden load increase
run_spike_test() {
    print_header "Spike Test (Resilience)"
    echo "Testing system resilience to sudden load spikes..."
    echo ""
    
    k6 run --stage "0s:100,10s:10000,20s:100,30s:0" \
        --out json="$REPORTS_DIR/spike-test-results.json" \
        "$SCENARIOS_DIR/api-rest.js"
    
    echo ""
    echo -e "${GREEN}✅ Spike test completed${NC}"
}

# Show all results
show_results() {
    print_header "Test Results Summary"
    
    if [ ! -d "$REPORTS_DIR" ] || [ -z "$(ls -A $REPORTS_DIR 2>/dev/null)" ]; then
        echo "No test results found."
        echo "Run some tests first!"
        exit 0
    fi
    
    echo "Available test results:"
    echo ""
    
    for file in "$REPORTS_DIR"/*.json; do
        if [ -f "$file" ]; then
            local filename=$(basename "$file")
            local size=$(du -h "$file" | cut -f1)
            local modified=$(stat -c %y "$file" 2>/dev/null || stat -f "%Sm" "$file")
            
            echo -e "  📊 ${GREEN}$filename${NC}"
            echo "     Size: $size"
            echo "     Modified: $modified"
            echo ""
        fi
    done
}

# Clean old results
clean_results() {
    print_header "Clean Test Results"
    
    if [ -d "$REPORTS_DIR" ]; then
        echo "Removing old test results..."
        rm -rf "$REPORTS_DIR"/*.json
        echo -e "${GREEN}✅ Test results cleaned${NC}"
    else
        echo "No results to clean."
    fi
}

# Main script
main() {
    check_k6
    
    case "${1:-help}" in
        smoke)
            run_smoke_test
            ;;
        api|api-rest)
            run_api_test
            ;;
        tr069)
            run_tr069_test
            ;;
        tr369|usp)
            run_tr369_test
            ;;
        mixed|production)
            run_mixed_test
            ;;
        soak)
            run_soak_test
            ;;
        spike)
            run_spike_test
            ;;
        custom)
            run_custom_test "$@"
            ;;
        results)
            show_results
            ;;
        clean)
            clean_results
            ;;
        help|*)
            echo "ACS Load Testing - Test Runner"
            echo ""
            echo "Usage: $0 <command> [options]"
            echo ""
            echo "Commands:"
            echo "  smoke          Quick smoke test (10 VUs, 30s)"
            echo "  api            REST API load test (1K users, ~28 min)"
            echo "  tr069          TR-069 protocol test (50K devices, ~55 min)"
            echo "  tr369          TR-369 USP protocol test (30K sessions, ~50 min)"
            echo "  mixed          Mixed protocol test (100K devices, ~75 min)"
            echo "  soak           24-hour stability test (5K devices)"
            echo "  spike          Sudden load spike test"
            echo "  custom <scenario> [vus] [duration]"
            echo "  results        Show all test results"
            echo "  clean          Remove old test results"
            echo ""
            echo "Examples:"
            echo "  $0 smoke"
            echo "  $0 api"
            echo "  $0 tr369"
            echo "  $0 custom api-rest 500 5m"
            echo ""
            ;;
    esac
}

main "$@"
