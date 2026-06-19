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
            'Authorization': 'Bearer 4|xvANh0rhnq5Fn4cyEOokR4XL8YOrus0YZEKWvt5Aff4b65f1',
        },
    };

    const res = http.get(
        'http://127.0.0.1:8000/api/v1/products/popular',
        params
    );

    check(res, {
        'status is valid': (r) =>
            r.status === 200 || r.status === 401,
    });
}