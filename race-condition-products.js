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

    const productId = 2;

    const payload = JSON.stringify({
        quantity: 1,
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer 2|uP9fmEiDqS7vIEOo2jlMxT6fRxBaqmtAcm2pRHNw7b27f565',
        },
    };

    const res = http.put(
        `http://ecommerce.local/api/v1/products/decrease-stock/${productId}`,
        // `http://127.0.0.1:8000/api/v1/products/decrease-stock/${productId}`,
        payload,
        params
    );

    check(res, {
        'status is valid': (r) =>
            r.status === 200 ||
            r.status === 422,
    });

    sleep(1);
}