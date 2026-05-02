import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

// custom counters
const totalRequests = new Counter('total_requests');
const success200 = new Counter('success_200');
const rejected429 = new Counter('rejected_429');
const failedOther = new Counter('failed_other');

export const options = {
    stages: [
        { duration: '10s', target: 10 },//1000 users looping requests for 10 seconds
        { duration: '10s', target: 15 },
        { duration: '5s', target: 0 },
    ],
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
            'Authorization': 'Bearer 1|opMGLaHYXnRCY25mW7G6yTH48HHO2wDqz6q8n3RZbee41824',
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