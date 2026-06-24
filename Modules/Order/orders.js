import http from 'k6/http';

import {
    BASE_URL,
    PRODUCT_IDS,
    ORDER_IDS,
    orderTime,
    requestOptions,
    recordResult,
    randomItem,
} from '../../support.js';

const knownOrderIds = [...ORDER_IDS];

export function testOrders() {
    const choice = Math.random() * 100;
    let response;

    if (choice < 25) {
        response = getMyOrders();
    } else if (choice < 50) {
        response = getOrderById();
    } else if (choice < 75) {
        response = createOrder();
    } else {
        response = cancelOrder();
    }

    recordResult(response, orderTime, 'Orders API');
}

function getMyOrders() {
    const response = http.get(
        `${BASE_URL}/orders/my`,
        requestOptions('orders', 'GET /orders/my')
    );

    rememberOrdersFromList(response);

    return response;
}

function getOrderById() {
    const orderId = randomItem(knownOrderIds);

    return http.get(
        `${BASE_URL}/orders/${orderId}`,
        requestOptions('orders', 'GET /orders/{id}')
    );
}

function createOrder() {
    const body = JSON.stringify({
        items: [
            {
                product_id: randomItem(PRODUCT_IDS),
                quantity: 1,
            },
        ],
    });

    const response = http.post(
        `${BASE_URL}/orders`,
        body,
        requestOptions('orders', 'POST /orders')
    );

    rememberCreatedOrder(response);

    return response;
}

function cancelOrder() {
    const orderId = randomItem(knownOrderIds);

    return http.post(
        `${BASE_URL}/orders/${orderId}/cancel`,
        null,
        requestOptions('orders', 'POST /orders/{id}/cancel')
    );
}

function rememberCreatedOrder(response) {
    if (response.status < 200 || response.status >= 300) {
        return;
    }

    try {
        const body = response.json();

        const orderId = Number(
            body?.data?.id ??
            body?.order?.id ??
            body?.id
        );

        addOrderId(orderId);
    } catch (_) {
        // Ignore response-shape differences.
    }
}

function rememberOrdersFromList(response) {
    if (response.status < 200 || response.status >= 300) {
        return;
    }

    try {
        const body = response.json();
        const data = body?.data;

        const orders = Array.isArray(data)
            ? data
            : Array.isArray(data?.data)
                ? data.data
                : [];

        for (const order of orders) {
            addOrderId(Number(order?.id));
        }
    } catch (_) {
        // Ignore response-shape differences.
    }
}

function addOrderId(orderId) {
    if (
        Number.isInteger(orderId) &&
        orderId > 0 &&
        !knownOrderIds.includes(orderId)
    ) {
        knownOrderIds.push(orderId);
    }

    if (knownOrderIds.length > 100) {
        knownOrderIds.shift();
    }
}
