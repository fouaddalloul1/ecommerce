import { sleep } from 'k6';

import { testProducts } from './Modules/Product/products.js';
import { testCategories } from './Modules/Category/categories.js';
import { testOrders } from './Modules/Order/orders.js';
import { randomNumber } from './support.js';

export const options = {
    stages: [
        { duration: '30s', target: 50 },
        { duration: '1m', target: 50 },
        { duration: '30s', target: 100 },
        { duration: '2m', target: 100 },
        { duration: '30s', target: 0 },
    ],

    summaryTrendStats: ['avg', 'p(95)', 'p(99)', 'max'],

    thresholds: {
        api_error_rate: ['rate<0.05'],
        http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    },
};

export default function () {
    const choice = Math.random() * 100;

    if (choice < 50) {
        testProducts();
    } else if (choice < 70) {
        testCategories();
    } else {
        testOrders();
    }

    sleep(randomNumber(0.3, 1));
}
