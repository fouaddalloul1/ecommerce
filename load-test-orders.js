
//  k6 run load-test-orders.js
import http from 'k6/http';
import { check, sleep } from 'k6';

function getMetrics() {
    const res = http.get('http://127.0.0.1:8000/metrics/redis');
    return res.json();
}

// ⏱ stage based on time (stable + deterministic)
let startTime = new Date();
let lastStage = null;

function detectStage() {

    const elapsed = (new Date() - startTime) / 1000;

    if (elapsed < 60) return 'STAGE_1';
    if (elapsed < 140) return 'STAGE_2';
    if (elapsed < 220) return 'STAGE_3';
    if (elapsed < 300) return 'STAGE_4';
    return 'STAGE_5';
}

/**
 *     stages: [
        { duration: '30s', target: 20 },  // رفع تدريجي لـ 20 مستخدم
        { duration: '1m', target: 50 },   // 50 مستخدم مؤقت
        { duration: '30s', target: 0 },   // خفض
    ],
 */
export const options = {
    stages: [
        { duration: '1m', target: 20 },
        { duration: '20s', target: 0 },

        { duration: '1m', target: 30 },
        { duration: '20s', target: 0 },

        { duration: '1m', target: 50 },
        { duration: '20s', target: 0 },

        { duration: '1m', target: 80 },
        { duration: '20s', target: 0 },

        { duration: '1m', target: 100 },
        { duration: '20s', target: 0 },
    ],


    thresholds: {
        http_req_duration: ['p(95)<1500'], // 95% من الطلبات أقل من 1 ثانية
    },
};

export default function () {

    const stage = detectStage();
    const metrics = getMetrics();

    // 🔥 PRINT ONLY ONCE PER STAGE
    if (stage !== lastStage) {

        console.log('\n====================');
        console.log(`NEW STAGE: ${stage}`);
        console.log(`CPU LOAD: ${metrics.cpu}`);
        console.log(`RAM LOAD: ${metrics.ram}`);
        console.log(`SYSTEM STATE: ${metrics.state}`);
        console.log('====================\n');

        lastStage = stage;
    }

    const res =
        //http.post('http://127.0.0.1:8000/api/v1/orders',
        http.post(`http://ecommerce.local/api/v1/orders`,

            JSON.stringify({ items: [{ product_id: 1, quantity: 1 }] }),
            {
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer 5|aGWx2y3Kw1TdocUVIPCZ5YeFDKsRoNEV0L4rQ3p96b540e4d',
                },
            }
        );

    check(res, {
        'valid response': (r) => r.status === 200 || r.status === 429,
    });

    sleep(1);
}