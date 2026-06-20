import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

/* =========================================================
   1. TEST DATA - Edit these values directly
   ========================================================= */
const BASE_URL = 'http://127.0.0.1:8000/api/v1';
const TOKEN = '8|G5nVhFzey3fkwnKpALBPgy3NoCk7lP4oYSalBuBJb4ef4b18';

const PRODUCT_IDS = [1, 2, 3, 4, 5];
const CATEGORY_IDS = [1, 2, 3];
const ORDER_IDS = [1, 2, 3];

/* =========================================================
   2. METRICS
   k6 already prints avg, p95, p99, RPS and error rate.
   These metrics separate the three modules.
   ========================================================= */
const productTime = new Trend('product_response_time', true);
const categoryTime = new Trend('category_response_time', true);
const orderTime = new Trend('order_response_time', true);
const apiErrors = new Rate('api_error_rate');

/* =========================================================
   3. LOAD PROFILE
   Ramp: 0 -> 50 users
   Steady: 50 users
   Stress: 50 -> 100 users
   Hold: 100 concurrent users
   ========================================================= */
export const options = {
    stages: [
        { duration: '30s', target: 50 },
        { duration: '1m', target: 50 },
        { duration: '30s', target: 100 },
        { duration: '2m', target: 100 },
        { duration: '30s', target: 0 },
    ],

    summaryTrendStats: ['avg', 'p(95)', 'p(99)', 'max'],

    thresholds: {
        api_error_rate: ['rate<0.05'],
        http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    },
};

/* =========================================================
   4. MAIN TRAFFIC DISTRIBUTION
   50% Products - 20% Categories - 30% Orders
   ========================================================= */
export default function () {
    const choice = Math.random() * 100;

    if (choice < 50) {
        testProducts();
    } else if (choice < 70) {
        testCategories();
    } else {
        testOrders();
    }

    // Realistic delay between user actions.
    sleep(randomNumber(0.3, 1));
}

/* =========================================================
   5. PRODUCTS - 50% OF TOTAL TRAFFIC
   ========================================================= */
function testProducts() {
    const choice = Math.random() * 4;
    let response;

    if (choice < 1) {
        response = http.get(`${BASE_URL}/products`, requestOptions('products', 'GET /products'));
    } else if (choice < 2) {
        const productId = randomItem(PRODUCT_IDS);
        response = http.get(`${BASE_URL}/products/${productId}`, requestOptions('products', 'GET /products/{id}'));
    } else if (choice < 3) {
        response = http.get(`${BASE_URL}/products/popular`, requestOptions('products', 'GET /products/popular'));
    } else {
        const categoryId = randomItem(CATEGORY_IDS);
        response = http.get(
            `${BASE_URL}/categories/${categoryId}/products`,
            requestOptions('products', 'GET /categories/{id}/products')
        );
    }

    recordResult(response, productTime, 'Products API');
}

/* =========================================================
   6. CATEGORIES - 20% OF TOTAL TRAFFIC
   ========================================================= */
function testCategories() {
    let response;

    if (Math.random() < 0.6) {
        response = http.get(`${BASE_URL}/categories`, requestOptions('categories', 'GET /categories'));
    } else {
        const categoryId = randomItem(CATEGORY_IDS);
        response = http.get(
            `${BASE_URL}/categories/${categoryId}`,
            requestOptions('categories', 'GET /categories/{id}')
        );
    }

    recordResult(response, categoryTime, 'Categories API');
}

/* =========================================================
   7. ORDERS - 30% OF TOTAL TRAFFIC
   ========================================================= */
function testOrders() {
    const choice = Math.random() * 4;
    let response;

    if (choice < 1) {
        response = http.get(`${BASE_URL}/orders/my`, requestOptions('orders', 'GET /orders/my'));
    } else if (choice < 2) {
        const orderId = randomItem(ORDER_IDS);
        response = http.get(`${BASE_URL}/orders/${orderId}`, requestOptions('orders', 'GET /orders/{id}'));
    } else if (choice < 3) {
        const body = JSON.stringify({
            items: [
                {
                    product_id: randomItem(PRODUCT_IDS),
                    quantity: 1,
                },
            ],
        });

        response = http.post(`${BASE_URL}/orders`, body, requestOptions('orders', 'POST /orders'));
    } else {
        const orderId = randomItem(ORDER_IDS);
        response = http.post(
            `${BASE_URL}/orders/${orderId}/cancel`,
            null,
            requestOptions('orders', 'POST /orders/{id}/cancel')
        );
    }

    recordResult(response, orderTime, 'Orders API');
}

/* =========================================================
   8. SHARED HELPERS
   ========================================================= */
function requestOptions(moduleName, endpointName) {
    return {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${TOKEN}`,
        },
        tags: {
            module: moduleName,
            endpoint: endpointName,
        },
    };
}

function recordResult(response, moduleTrend, checkName) {
    // 409 and 422 are business rejections, not server failures.
    const failed = response.status === 0 || response.status === 401 || response.status === 403 || response.status >= 500;

    moduleTrend.add(response.timings.duration);
    apiErrors.add(failed);

    check(response, {
        [`${checkName} returned a valid response`]: () => !failed,
    });
}

function randomItem(items) {
    return items[Math.floor(Math.random() * items.length)];
}

function randomNumber(min, max) {
    return Math.random() * (max - min) + min;
}
