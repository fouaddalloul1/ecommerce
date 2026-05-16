import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

// custom counters
const totalRequests = new Counter('total_requests');
const success200 = new Counter('success_200');
const rejected429 = new Counter('rejected_429');
const failedOther = new Counter('failed_other');

/**
 *     stages: [
        { duration: '30s', target: 20 },  // رفع تدريجي لـ 20 مستخدم
        { duration: '1m', target: 50 },   // 50 مستخدم مؤقت
        { duration: '30s', target: 0 },   // خفض
    ],
 */
export const options = {
    stages: [
        { duration: '10s', target: 40 },//40 users looping requests for 10 seconds
        { duration: '10s', target: 50 },
        { duration: '5s', target: 0 },
    ],


        thresholds: {
        http_req_duration: ['p(95)<1500'], // 95% من الطلبات أقل من 1 ثانية
    },
};

export default function () {
    const url = 'http://127.0.0.1:8000/api/v1/orders';

    const payload = JSON.stringify({
        items: [
            {
                product_id: 1,
                quantity: 1
            }
        ]
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer uRv2QhykTkwBRzvEduSVOW91oDwY1NaAjSvfFjd1d1c07d5b',
            'Accept': 'application/json',
        },
    };

    const res = http.post(url, payload, params);

    // metrics
    totalRequests.add(1);

    if (res.status === 200) {
        success200.add(1);
    } else if (res.status === 429) {
        rejected429.add(1);
    } else {
        failedOther.add(1);
    }

    check(res, {
        'status is 200 or 429': (r) =>
            r.status === 200 || r.status === 429,
    });
}

// k6 run load-test-orders.js
