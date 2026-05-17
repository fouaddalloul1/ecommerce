import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 20 },  // رفع تدريجي لـ 20 مستخدم
        { duration: '1m', target: 50 },   // 50 مستخدم مؤقت
        { duration: '30s', target: 0 },   // خفض
    ],
    thresholds: {
        http_req_duration: ['p(95)<1000'], // 95% من الطلبات أقل من 1 ثانية
    },
};

export default function () {
    const url = 'http://localhost:8000/api/orders';
    const payload = JSON.stringify({
        user_id: 1,
        items: [['product_id' => 1, 'quantity' => 2]],
    });
    const params = { headers: { 'Content-Type': 'application/json' } };

    const res = http.post(url, payload, params);
    check(res, { 'status was 200': (r) => r.status === 200 });

    sleep(1);
}
