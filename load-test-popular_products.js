import http from 'k6/http';
import { check } from 'k6';

export const options = {
    vus: 50,          // 50 مستخدم وهمي
    duration: '20s',  // لمدة 20 ثانية
};

export default function () {

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer 6|cKusaEHK4BQKEmftVdzDZtC4mxvoCPzXJ74i0yzs0d94305d',
        },
    };

    const res = http.get(
        // 'http://127.0.0.1:8000/api/v1/products/popular', //use with load balance 
        'http://ecommerce.local:8001/api/v1/products/popular', //use without load balance

        params
    );

    check(res, {
        'status is valid': (r) =>
            r.status === 200 || r.status === 401,
    });
}