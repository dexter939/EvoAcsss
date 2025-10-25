#!/bin/bash

#
# K6 Load Testing with Prometheus Integration
# 
# This script runs K6 load tests and exports metrics to Prometheus in real-time
# 
# Usage:
#   ./tests/Load/run-with-prometheus.sh <scenario> [options]
# 
# Examples:
#   ./tests/Load/run-with-prometheus.sh api
#   ./tests/Load/run-with-prometheus.sh mixed
#   ./tests/Load/run-with-prometheus.sh tr369
# 

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCENARIO=${1:-smoke}
K6_METRICS_PORT=${K6_METRICS_PORT:-9091}
JSON_OUTPUT_DIR="tests/Load/reports"
JSON_OUTPUT_FILE="${JSON_OUTPUT_DIR}/test-results-$(date +%Y%m%d-%H%M%S).json"

# Create reports directory
mkdir -p "$JSON_OUTPUT_DIR"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}K6 Load Testing with Prometheus${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Scenario: ${GREEN}${SCENARIO}${NC}"
echo -e "Metrics Port: ${GREEN}${K6_METRICS_PORT}${NC}"
echo -e "JSON Output: ${GREEN}${JSON_OUTPUT_FILE}${NC}"
echo ""

# Select test scenario
case $SCENARIO in
    smoke)
        SCENARIO_FILE="tests/Load/scenarios/api-rest.js"
        echo -e "${YELLOW}Running smoke test (REST API, low load)...${NC}"
        ;;
    api)
        SCENARIO_FILE="tests/Load/scenarios/api-rest.js"
        echo -e "${YELLOW}Running REST API load test...${NC}"
        ;;
    tr069)
        SCENARIO_FILE="tests/Load/scenarios/tr069.js"
        echo -e "${YELLOW}Running TR-069 protocol test...${NC}"
        ;;
    tr369)
        SCENARIO_FILE="tests/Load/scenarios/tr369.js"
        echo -e "${YELLOW}Running TR-369 USP protocol test...${NC}"
        ;;
    mixed)
        SCENARIO_FILE="tests/Load/scenarios/mixed.js"
        echo -e "${YELLOW}Running mixed protocol test (100K devices)...${NC}"
        ;;
    soak)
        SCENARIO_FILE="tests/Load/scenarios/mixed.js"
        echo -e "${YELLOW}Running 24h soak test...${NC}"
        ;;
    *)
        echo -e "${RED}Unknown scenario: ${SCENARIO}${NC}"
        echo "Available scenarios: smoke, api, tr069, tr369, mixed, soak"
        exit 1
        ;;
esac

# Check if K6 is installed
if ! command -v k6 &> /dev/null; then
    echo -e "${RED}Error: k6 is not installed${NC}"
    echo "Run: ./tests/Load/install-k6.sh"
    exit 1
fi

# Check if scenario file exists
if [ ! -f "$SCENARIO_FILE" ]; then
    echo -e "${RED}Error: Scenario file not found: ${SCENARIO_FILE}${NC}"
    exit 1
fi

# Step 1: Start Prometheus exporter in background
echo -e "${BLUE}Step 1/3: Starting Prometheus exporter...${NC}"
echo "Metrics will be available at: http://localhost:${K6_METRICS_PORT}/metrics"

# Kill existing exporter if running
pkill -f "prometheus-exporter.js" 2>/dev/null || true
sleep 1

# Start exporter (will watch the JSON file)
node tests/Load/utils/prometheus-exporter.js "$JSON_OUTPUT_FILE" &
EXPORTER_PID=$!

# Wait for exporter to start
sleep 2

# Check if exporter is running
if ! kill -0 $EXPORTER_PID 2>/dev/null; then
    echo -e "${RED}Error: Failed to start Prometheus exporter${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Prometheus exporter started (PID: ${EXPORTER_PID})${NC}"

# Cleanup function
cleanup() {
    echo ""
    echo -e "${YELLOW}Cleaning up...${NC}"
    kill $EXPORTER_PID 2>/dev/null || true
    echo -e "${GREEN}✓ Done${NC}"
}
trap cleanup EXIT

# Step 2: Run K6 test with JSON output
echo ""
echo -e "${BLUE}Step 2/3: Running K6 test...${NC}"
echo "Test output will be saved to: ${JSON_OUTPUT_FILE}"
echo ""

# Run K6 with JSON output
k6 run \
    --out "json=${JSON_OUTPUT_FILE}" \
    --quiet \
    "$SCENARIO_FILE"

K6_EXIT_CODE=$?

# Step 3: Display results
echo ""
echo -e "${BLUE}Step 3/3: Test Results${NC}"
echo -e "${BLUE}========================================${NC}"

if [ $K6_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ Test completed successfully${NC}"
else
    echo -e "${RED}✗ Test failed with exit code: ${K6_EXIT_CODE}${NC}"
fi

echo ""
echo -e "${BLUE}Prometheus Metrics:${NC}"
echo -e "  View metrics: ${GREEN}http://localhost:${K6_METRICS_PORT}/metrics${NC}"
echo -e "  Health check: ${GREEN}http://localhost:${K6_METRICS_PORT}/health${NC}"
echo ""
echo -e "${BLUE}Grafana Dashboard:${NC}"
echo -e "  Open Grafana: ${GREEN}http://localhost:3000${NC}"
echo -e "  Dashboard: ${GREEN}ACS - K6 Load Testing${NC}"
echo ""
echo -e "${BLUE}JSON Report:${NC}"
echo -e "  File: ${GREEN}${JSON_OUTPUT_FILE}${NC}"
echo ""

# Keep exporter running for a few more seconds
echo -e "${YELLOW}Keeping metrics exporter running for 30 seconds...${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
sleep 30

exit $K6_EXIT_CODE
