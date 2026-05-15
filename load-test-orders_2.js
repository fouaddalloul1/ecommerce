import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

// custom counters
const totalRequests = new Counter('total_requests');
const success200 = new Counter('success_200');
const rejected429 = new Counter('rejected_429');
const failedOther = new Counter('failed_other');

export const options = {
    stages: [
        { duration: '15s', target: 20 },   // رفع تدريجي لـ 20 مستخدم
        { duration: '30s', target: 50 },   // 50 مستخدم لمدة 30 ثانية
        { duration: '15s', target: 80 },   // رفع لـ 80 مستخدم (ذروة)
        { duration: '30s', target: 80 },   // ثبات عند الذروة
        { duration: '15s', target: 0 },    // خفض تدريجي
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
            'Authorization': 'Bearer Ewk2KsARA0o4CJMeXNCamhAOPpSCZJ9CNO9j63Z774b9d1f0',
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
