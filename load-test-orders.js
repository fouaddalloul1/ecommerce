// import http from 'k6/http';
// import { check } from 'k6';
// import { Counter } from 'k6/metrics';

// // custom counters
// const totalRequests = new Counter('total_requests');
// const success200 = new Counter('success_200');
// const rejected429 = new Counter('rejected_429');
// const failedOther = new Counter('failed_other');

// export const options = {
//     stages: [
//         { duration: '10s', target: 40 },//40 users looping requests for 10 seconds
//         { duration: '10s', target: 50 },
//         { duration: '5s', target: 0 },
//     ],
// };

// export default function () {
//     const url = 'http://127.0.0.1:8000/api/v1/orders';

//     const payload = JSON.stringify({
//         items: [
//             {
//                 product_id: 1,
//                 quantity: 1
//             }
//         ]
//     });

//     const params = {
//         headers: {
//             'Content-Type': 'application/json',
//             'Authorization': 'Bearer 4|8RM2khPePPkpEuEiEldLtNnyoZkJ6RHq8ZbmZH6if16c4420',
//             'Accept': 'application/json',
//         },
//     };

//     const res = http.post(url, payload, params);

//     // metrics
//     totalRequests.add(1);

//     if (res.status === 200) {
//         success200.add(1);
//     } else if (res.status === 429) {
//         rejected429.add(1);
//     } else {
//         failedOther.add(1);
//     }

//     check(res, {
//         'status is 200 or 429': (r) =>
//             r.status === 200 || r.status === 429,
//     });
// }

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

    const res = http.post('http://127.0.0.1:8000/api/v1/orders',
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