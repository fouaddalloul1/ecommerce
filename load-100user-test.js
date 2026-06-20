import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    scenarios: {
        browse_products: {
            executor: 'ramping-vus',
            exec: 'browse_products',   // تحديد الدالة
            startVUs: 0,
            stages: [
                { duration: '30s', target: 50 },
                { duration: '60s', target: 50 },
                { duration: '30s', target: 0 },
            ],
        },
        create_orders: {
            executor: 'ramping-vus',
            exec: 'create_orders',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 25 },
                { duration: '60s', target: 25 },
                { duration: '30s', target: 0 },
            ],
        },
        order_details: {
            executor: 'ramping-vus',
            exec: 'order_details',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 15 },
                { duration: '60s', target: 15 },
                { duration: '30s', target: 0 },
            ],
        },
        popular_products: {
            executor: 'ramping-vus',
            exec: 'popular_products',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 10 },
                { duration: '60s', target: 10 },
                { duration: '30s', target: 0 },
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<2000'],
        http_req_failed: ['rate<0.01'],
    },
};

const BASE_URL = 'http://127.0.0.1:8001';
const TOKEN = __ENV.TOKEN || '';

if (!TOKEN) {
    throw new Error('Please provide TOKEN using -e TOKEN="your_token"');
}

// دالة تصفح المنتجات
export function browse_products() {
    const res = http.get(`${BASE_URL}/api/v1/products?q=shirt&category_id=3`, {
        headers: {
            'Authorization': `Bearer ${TOKEN}`,
            'Accept': 'application/json',
        },
    });
    check(res, { 'browse products status 200': (r) => r.status === 200 });
    sleep(1);
}

// دالة إنشاء طلب
export function create_orders() {
    const payload = JSON.stringify({
        items: [{ product_id: 1, quantity: 1 }],
    });
    const res = http.post(`${BASE_URL}/api/v1/orders`, payload, {
        headers: {
            'Authorization': `Bearer ${TOKEN}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    });
    check(res, { 'create order status 200 or 201': (r) => r.status === 200 || r.status === 201 });
    sleep(2);
}

// دالة عرض تفاصيل طلب
export function order_details() {
    const res = http.get(`${BASE_URL}/api/v1/orders/my`, {
        headers: {
            'Authorization': `Bearer ${TOKEN}`,
            'Accept': 'application/json',
        },
    });
    check(res, { 'order details status 200': (r) => r.status === 200 });
    sleep(1);
}

// دالة عرض المنتجات الشائعة
export function popular_products() {
    const res = http.get(`${BASE_URL}/api/v1/products/popular`, {
        headers: {
            'Authorization': `Bearer ${TOKEN}`,
            'Accept': 'application/json',
        },
    });
    check(res, { 'popular products status 200': (r) => r.status === 200 });
    sleep(1);
}
