import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '10s', target: 50 },
        { duration: '5s', target: 50 },
        { duration: '10s', target: 0 },
    ],
};

export default function () {

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer 2|uP9fmEiDqS7vIEOo2jlMxT6fRxBaqmtAcm2pRHNw7b27f565',
        },
    };

    const res = http.get(
        `http://127.0.0.1:8000/api/v1/categories`,
        //  `http://ecommerce.local:8000/api/v1/categories`,
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