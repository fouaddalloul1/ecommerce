import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '10s', target: 100 },
        { duration: '5s', target: 75 },
        { duration: '10s', target: 0 },
    ],
};



function getMetrics() {
    const res = http.get('http://127.0.0.1:8001/metrics/redis');
    return res.json();
}


export default function () {


    const metrics = getMetrics();


    console.log('\n====================');
    console.log(`CPU LOAD: ${metrics.cpu}`);
    console.log(`RAM LOAD: ${metrics.ram}`);
    console.log(`SYSTEM STATE: ${metrics.state}`);
    console.log('====================\n');



    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer 7|JQ1TfNaIGBUaOcAlLqsUtAApAhWILCYqd19Ad466415fbff0',
        },
    };

    const res = http.get(
        // `http://127.0.0.1:8001/api/v1/categories`, // manage reousrces
        `http://ecommerce.local:8000/api/v1/categories`, // load balance
        params
    );

    check(res, {
        'status is valid': (r) =>
            r.status === 200 ||
            r.status === 401, // في حال التوكين غير صالح
    });

    sleep(1);
}

//k6 run Load-distribution-categories.js

// powershell -Command "Select-String -Path 'storage/logs/laravel.log' -Pattern 'port: 8003' | Measure-Object | Select-Object -ExpandProperty Count"