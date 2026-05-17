import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

// custom counters
const totalRequests = new Counter('total_requests');
const success200 = new Counter('success_200');
const rejected429 = new Counter('rejected_429');
const failedOther = new Counter('failed_other');
/**
    stages: [
        { duration: '5s', target: 25 },   // رفع تدريجي لـ 5 مستخدمين
        { duration: '15s', target: 50 }, // ذروة خفيفة (10 مستخدمين)
        { duration: '15s', target: 60 }, // ذروة خفيفة (10 مستخدمين)
        { duration: '10s', target: 50 }, // ذروة خفيفة (10 مستخدمين)
        { duration: '5s', target: 0 },   // خفض تدريجي
    ],
 */
export const options = {
    stages: [
        { duration: '5s', target: 25 },   // رفع تدريجي لـ 5 مستخدمين
        { duration: '15s', target: 45 }, // ذروة خفيفة (10 مستخدمين)
        { duration: '10s', target: 50 }, // ذروة خفيفة (10 مستخدمين)
        { duration: '10s', target: 35 }, // ذروة خفيفة (10 مستخدمين)
        { duration: '10s', target: 0 },   // خفض تدريجي
    ],
    thresholds: {
        http_req_duration: ['p(95)<1000'], // 95% من الطلبات أقل من 1 ثانية
        http_req_failed: ['rate<0.01'],    // نسبة الفشل أقل من 1%
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
            'Authorization': 'Bearer nOvW2Z2EPt1QSpR6JtQJE9h73bQxWxR1zxlJahvpd165e579',
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
        'status is 200': (r) => r.status === 200,
        'response time < 1s': (r) => r.timings.duration < 1000,
    });

    sleep(0.5);  // انتظار نصف ثانية بين الطلبات لمحاكاة واقعية
}
