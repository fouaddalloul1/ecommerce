#!/bin/bash

# ============================================================
# Simple Script - No Auto Setup
# You are responsible for configuring Horizon and workers manually
# ============================================================

PROJECT_PATH="~/ecommers"
cd "$PROJECT_PATH"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============================================================
# Helper Functions
# ============================================================

print_header() {
    echo ""
    echo "=========================================="
    echo -e "${BLUE}$1${NC}"
    echo "=========================================="
}

pause() {
    echo ""
    read -p "Press Enter to continue..."
}

# Measure current resources
measure_resources() {
    local label=$1
    local timestamp=$(date +%H:%M:%S)

    local cpu=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    local ram_used=$(free -m | awk 'NR==2{print $3}')
    local ram_total=$(free -m | awk 'NR==2{print $2}')
    local ram_percent=$(echo "scale=2; $ram_used * 100 / $ram_total" | bc)
    local horizon_workers=$(ps aux | grep "horizon:work" | grep -v grep | wc -l)

    echo "$timestamp,$label,$cpu,$ram_used,$ram_percent,$horizon_workers" >> "$PROJECT_PATH/measurements.csv"

    echo -e "${GREEN}📊 $label: CPU=${cpu}%, RAM=${ram_used}MB (${ram_percent}%), Horizon Workers=${horizon_workers}${NC}"
}

# Extract k6 results from JSON file
extract_k6_results() {
    local json_file=$1
    local test_name=$2

    if [ ! -f "$json_file" ]; then
        echo -e "${RED}❌ File $json_file not found${NC}"
        return 1
    fi

    # Try to extract numbers using multiple methods
    local p95=$(grep -oE '"p\(95\)":[0-9.]+' "$json_file" | head -1 | cut -d':' -f2)
    local avg=$(grep -oE '"avg":[0-9.]+' "$json_file" | head -1 | cut -d':' -f2)
    local total_requests=$(grep -oE '"http_reqs":[0-9]+' "$json_file" | head -1 | cut -d':' -f2)

    # If failed, try other formats
    if [ -z "$p95" ]; then
        p95=$(grep -oE 'p\(95\)[^0-9]*[0-9.]+' "$json_file" | head -1 | grep -oE '[0-9.]+')
    fi
    if [ -z "$avg" ]; then
        avg=$(grep -oE 'avg[^0-9]*[0-9.]+' "$json_file" | head -1 | grep -oE '[0-9.]+')
    fi
    if [ -z "$total_requests" ]; then
        total_requests=$(grep -oE 'http_reqs[^0-9]*[0-9]+' "$json_file" | head -1 | grep -oE '[0-9]+')
    fi

    # Store in CSV
    echo "$test_name,$p95,$avg,$total_requests,$(date +%H:%M:%S)" >> "$PROJECT_PATH/k6_results.csv"

    echo ""
    echo -e "${BLUE}📈 Test Results:${NC}"
    echo -e "   Response Time p95: ${YELLOW}${p95}s${NC}"
    echo -e "   Response Time avg: ${YELLOW}${avg}s${NC}"
    echo -e "   Total Requests: ${YELLOW}${total_requests}${NC}"
}

# ============================================================
# Main Test (without changing any settings)
# ============================================================

run_current_test() {
    local test_name=$1

    print_header "🚀 Starting Test: $test_name"

    echo -e "${YELLOW}⚠️ Please ensure:${NC}"
    echo "   1. Horizon is running (php artisan horizon)"
    echo "   2. Number of workers is set as desired in config/horizon.php"
    echo "   3. QUEUE_CONNECTION is set correctly in .env"
    echo ""
    read -p "Is everything ready? (Press Enter to continue)"

    # 1. Measure before test
    measure_resources "BEFORE_${test_name}"

    # 2. Run k6 test in background
    echo -e "${YELLOW}🔄 Running k6 test...${NC}"
    k6 run --out json="results_${test_name}.json" load-test-orders_2.js &
    K6_PID=$!

    # 3. Wait 10 seconds then measure resources during execution
    sleep 10
    measure_resources "DURING_${test_name}"

    # 4. Wait for k6 test to finish
    wait $K6_PID

    # 5. Measure after test
    measure_resources "AFTER_${test_name}"

    # 6. Extract results
    extract_k6_results "results_${test_name}.json" "$test_name"

    echo ""
    echo -e "${GREEN}✅ Test completed successfully${NC}"
}

# ============================================================
# Show saved results
# ============================================================

show_results() {
    print_header "📊 Saved Results"

    echo ""
    if [ -f "$PROJECT_PATH/k6_results.csv" ]; then
        echo -e "${BLUE}=== k6 Results ===${NC}"
        echo "----------------------------------------"
        printf "| %-20s | %-10s | %-10s | %-10s |\n" "Test Name" "p95(s)" "avg(s)" "Requests"
        echo "----------------------------------------"
        while IFS=',' read -r test p95 avg requests timestamp; do
            if [ "$test" != "test_name" ]; then
                printf "| %-20s | %-10s | %-10s | %-10s |\n" "$test" "$p95" "$avg" "$requests"
            fi
        done < "$PROJECT_PATH/k6_results.csv"
        echo "----------------------------------------"
    else
        echo -e "${YELLOW}⚠️ No saved results found${NC}"
    fi

    echo ""
    if [ -f "$PROJECT_PATH/measurements.csv" ]; then
        echo -e "${BLUE}=== Resource Measurements (Last 10) ===${NC}"
        tail -10 "$PROJECT_PATH/measurements.csv"
    fi

    pause
}

# ============================================================
# Export results
# ============================================================

export_results() {
    print_header "📋 Export Results for Report"

    local export_file="benchmark_export_$(date +%Y%m%d_%H%M%S).csv"

    echo "test_name,p95_response_sec,avg_response_sec,total_requests,measurement_time" > "$PROJECT_PATH/$export_file"
    tail -n +2 "$PROJECT_PATH/k6_results.csv" >> "$PROJECT_PATH/$export_file"

    echo -e "${GREEN}✅ Exported to: $export_file${NC}"
    echo ""
    echo -e "${BLUE}📋 Ready-to-use table for report:${NC}"
    echo ""
    echo "| Test Name | p95 Response | avg Response | Total Requests |"
    echo "|-----------|--------------|--------------|----------------|"

    tail -n +2 "$PROJECT_PATH/k6_results.csv" | while IFS=',' read -r test p95 avg requests timestamp; do
        echo "| $test | ${p95}s | ${avg}s | $requests |"
    done

    pause
}

# ============================================================
# Clear all results
# ============================================================

clear_results() {
    print_header "🗑️ Clear All Previous Results"

    echo -e "${YELLOW}⚠️ Are you sure? This will delete:${NC}"
    echo "   - results_*.json"
    echo "   - measurements.csv"
    echo "   - k6_results.csv"
    echo ""
    read -p "Type 'yes' to confirm: " confirm

    if [ "$confirm" = "yes" ]; then
        rm -f "$PROJECT_PATH"/results_*.json
        rm -f "$PROJECT_PATH"/measurements.csv
        rm -f "$PROJECT_PATH"/k6_results.csv
        echo -e "${GREEN}✅ All results deleted${NC}"
    else
        echo -e "${YELLOW}❌ Cancelled${NC}"
    fi

    pause
}

# ============================================================
# Initialize files
# ============================================================

init_files() {
    if [ ! -f "$PROJECT_PATH/k6_results.csv" ]; then
        echo "test_name,p95_response_sec,avg_response_sec,total_requests,timestamp" > "$PROJECT_PATH/k6_results.csv"
    fi
    if [ ! -f "$PROJECT_PATH/measurements.csv" ]; then
        echo "timestamp,stage,cpu_percent,ram_mb,ram_percent,horizon_workers" > "$PROJECT_PATH/measurements.csv"
    fi
}

# ============================================================
# Main Menu
# ============================================================

show_menu() {
    clear
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║        🚀 Simple Performance Benchmark - No Auto Setup       ║"
    echo "╠══════════════════════════════════════════════════════════════╣"
    echo "║                                                              ║"
    echo "║  You are responsible for preparing the environment:         ║"
    echo "║    • Start Horizon (php artisan horizon)                     ║"
    echo "║    • Set number of workers in config/horizon.php             ║"
    echo "║    • Set QUEUE_CONNECTION in .env                            ║"
    echo "║                                                              ║"
    echo "║  Options:                                                    ║"
    echo "║    1) Run a new test (enter test name/description)           ║"
    echo "║    2) Show saved results                                     ║"
    echo "║    3) Export results to CSV (ready for report)               ║"
    echo "║    4) Clear all previous results                             ║"
    echo "║    0) Exit                                                   ║"
    echo "║                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo ""
}

# ============================================================
# Main Program
# ============================================================

init_files

while true; do
    show_menu
    read -p "Choose option [0-4]: " choice

    case $choice in
        1)
            echo ""
            read -p "Enter test name/description (e.g., sync_1_1 or async_2_3): " test_name
            if [ -z "$test_name" ]; then
                echo -e "${RED}❌ Test name cannot be empty${NC}"
                pause
            else
                run_current_test "$test_name"
                pause
            fi
            ;;
        2)
            show_results
            ;;
        3)
            export_results
            ;;
        4)
            clear_results
            init_files
            ;;
        0)
            echo -e "${GREEN}👋 Goodbye!${NC}"
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Invalid option${NC}"
            sleep 1
            ;;
    esac
done

