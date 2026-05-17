#!/bin/bash

# ============================================================
# Interactive Performance Benchmark Script
# ============================================================

PROJECT_PATH="/run/media/kali/Fouad 2/A-Projects/ecommers"
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

# Measure resources
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

# Set number of workers
set_workers() {
    local pdf_workers=$1
    local email_workers=$2

    sed -i "s/'processes' => [0-9]\+\(\s*\/\/.*\)\?/'processes' => $pdf_workers/" config/horizon.php
    echo -e "${GREEN}✅ Invoices workers set to → $pdf_workers | Notifications workers set to → $email_workers${NC}"
}

# Extract k6 results
extract_k6_results() {
    local json_file=$1
    local test_name=$2

    if [ ! -f "$json_file" ]; then
        echo -e "${RED}❌ File $json_file not found${NC}"
        return 1
    fi

    local p95=$(grep -o '"p(95)".*' "$json_file" | head -1 | grep -o '[0-9.]\+' | head -1)
    local avg=$(grep -o '"avg".*' "$json_file" | head -1 | grep -o '[0-9.]\+' | head -1)
    local total_requests=$(grep -o '"http_reqs".*' "$json_file" | grep -o '[0-9]\+' | head -1)

    echo "$test_name,$p95,$avg,$total_requests,$(date +%H:%M:%S)" >> "$PROJECT_PATH/k6_results.csv"

    echo ""
    echo -e "${BLUE}📈 Test Results:${NC}"
    echo -e "   Response Time p95: ${YELLOW}${p95}s${NC}"
    echo -e "   Response Time avg: ${YELLOW}${avg}s${NC}"
    echo -e "   Total Requests: ${YELLOW}${total_requests}${NC}"
}

# Run k6 test with background monitoring
run_k6_test() {
    local test_name=$1

    echo -e "${YELLOW}🔄 Running k6 load test...${NC}"
    echo ""

    # Start resource monitoring in background
    (
        while true; do
            local ts=$(date +%H:%M:%S)
            local cpu=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
            local ram=$(free -m | awk 'NR==2{print $3}')
            echo "$ts,$test_name,$cpu,$ram" >> "$PROJECT_PATH/during_measurements.csv"
            sleep 2
        done
    ) &
    MONITOR_PID=$!

    # Run the test
    k6 run --out json="results_${test_name}.json" load-test-orders_2.js

    # Stop monitoring
    kill $MONITOR_PID 2>/dev/null

    # Extract results
    extract_k6_results "results_${test_name}.json" "$test_name"
}

# ============================================================
# Test Functions
# ============================================================

test_sync() {
    print_header "🧪 SYNC Test (Without Queues - Legacy Mode)"

    echo -e "${YELLOW}⚠️ This test will change QUEUE_CONNECTION to sync${NC}"
    echo "Meaning: PDF and Email will run inside the request (Horizon will be stopped)"

    # Change to sync
    sed -i 's/QUEUE_CONNECTION=redis/QUEUE_CONNECTION=sync/' .env
    php artisan config:clear

    # Stop Horizon
    php artisan horizon:terminate 2>/dev/null

    # Measure before
    measure_resources "BEFORE_SYNC"

    # Run test
    run_k6_test "sync"

    # Measure after
    measure_resources "AFTER_SYNC"

    # Change back to redis
    sed -i 's/QUEUE_CONNECTION=sync/QUEUE_CONNECTION=redis/' .env
    php artisan config:clear

    echo ""
    echo -e "${GREEN}✅ SYNC Test Completed${NC}"
}

test_scenario() {
    local scenario_name=$1
    local pdf_workers=$2
    local email_workers=$3
    local description=$4

    print_header "🧪 $scenario_name - $description"

    echo -e "${YELLOW}PDF Workers: $pdf_workers | Email Workers: $email_workers${NC}"

    # Update workers
    set_workers $pdf_workers $email_workers

    # Stop old Horizon
    php artisan horizon:terminate 2>/dev/null
    sleep 1

    # Measure before Horizon
    measure_resources "BEFORE_HORIZON_${scenario_name}"

    # Start Horizon
    php artisan horizon > /dev/null 2>&1 &
    HORIZON_PID=$!
    echo -e "${GREEN}✅ Horizon is running (PID: $HORIZON_PID)${NC}"
    sleep 3

    # Measure after Horizon started
    measure_resources "BEFORE_TEST_${scenario_name}"

    # Run test
    run_k6_test "$scenario_name"

    # Measure after test
    measure_resources "AFTER_TEST_${scenario_name}"

    # Stop Horizon
    kill $HORIZON_PID 2>/dev/null

    echo ""
    echo -e "${GREEN}✅ $scenario_name Test Completed${NC}"
}

# ============================================================
# Main Menu
# ============================================================

show_menu() {
    clear
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║     🚀 Laravel Performance Benchmark - Interactive Menu      ║"
    echo "╠══════════════════════════════════════════════════════════════╣"
    echo "║                                                              ║"
    echo "║  Available Tests:                                            ║"
    echo "║                                                              ║"
    echo "║  1) SYNC Test (Before - Without Queues)                      ║"
    echo "║     └─ All tasks run inside the request                      ║"
    echo "║                                                              ║"
    echo "║  2) Scenario A - Low Balance                                 ║"
    echo "║     └─ PDF: 1 worker  |  Email: 1 worker                     ║"
    echo "║                                                              ║"
    echo "║  3) Scenario B - Medium Balance ⭐ (Recommended)             ║"
    echo "║     └─ PDF: 2 workers |  Email: 3 workers                    ║"
    echo "║                                                              ║"
    echo "║  4) Scenario C - High Density                                ║"
    echo "║     └─ PDF: 3 workers |  Email: 6 workers                    ║"
    echo "║                                                              ║"
    echo "║  5) Show Saved Results                                       ║"
    echo "║                                                              ║"
    echo "║  6) Export Results to CSV (Ready for Report)                 ║"
    echo "║                                                              ║"
    echo "║  7) Clear All Previous Results (Fresh Start)                 ║"
    echo "║                                                              ║"
    echo "║  0) Exit                                                     ║"
    echo "║                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo ""
}

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

export_results() {
    print_header "📋 Export Results for Report"

    local export_file="benchmark_export_$(date +%Y%m%d_%H%M%S).csv"

    echo "test_name,p95_response_sec,avg_response_sec,total_requests,measurement_time" > "$PROJECT_PATH/$export_file"
    tail -n +2 "$PROJECT_PATH/k6_results.csv" >> "$PROJECT_PATH/$export_file"

    echo -e "${GREEN}✅ Results exported to: $export_file${NC}"
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

clear_results() {
    print_header "🗑️ Clear All Previous Results"

    echo -e "${YELLOW}⚠️ Are you sure? This will delete:${NC}"
    echo "   - results_*.json"
    echo "   - measurements.csv"
    echo "   - during_measurements.csv"
    echo "   - k6_results.csv"
    echo ""
    read -p "Type 'yes' to confirm: " confirm

    if [ "$confirm" = "yes" ]; then
        rm -f "$PROJECT_PATH"/results_*.json
        rm -f "$PROJECT_PATH"/measurements.csv
        rm -f "$PROJECT_PATH"/during_measurements.csv
        rm -f "$PROJECT_PATH"/k6_results.csv
        echo -e "${GREEN}✅ All results deleted${NC}"
    else
        echo -e "${YELLOW}❌ Cancelled${NC}"
    fi

    pause
}

# ============================================================
# File Initialization
# ============================================================

init_files() {
    if [ ! -f "$PROJECT_PATH/k6_results.csv" ]; then
        echo "test_name,p95_response_sec,avg_response_sec,total_requests,timestamp" > "$PROJECT_PATH/k6_results.csv"
    fi
    if [ ! -f "$PROJECT_PATH/measurements.csv" ]; then
        echo "timestamp,stage,cpu_percent,ram_mb,ram_percent,horizon_workers" > "$PROJECT_PATH/measurements.csv"
    fi
    if [ ! -f "$PROJECT_PATH/during_measurements.csv" ]; then
        echo "timestamp,test_name,cpu_percent,ram_mb" > "$PROJECT_PATH/during_measurements.csv"
    fi
}

# ============================================================
# Main Program
# ============================================================

init_files

while true; do
    show_menu
    read -p "🔹 Choose test number [0-7]: " choice

    case $choice in
        1)
            test_sync
            pause
            ;;
        2)
            test_scenario "scenario_A_1_1" 1 1 "Low Balance"
            pause
            ;;
        3)
            test_scenario "scenario_B_2_3" 2 3 "Medium Balance ⭐"
            pause
            ;;
        4)
            test_scenario "scenario_C_3_6" 3 6 "High Density"
            pause
            ;;
        5)
            show_results
            ;;
        6)
            export_results
            ;;
        7)
            clear_results
            init_files
            ;;
        0)
            echo -e "${GREEN}👋 Goodbye!${NC}"
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Invalid option, try again${NC}"
            sleep 1
            ;;
    esac
done
