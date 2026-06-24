import http from 'k6/http';

import {
    BASE_URL,
    PRODUCT_IDS,
    CATEGORY_IDS,
    productTime,
    requestOptions,
    recordResult,
    randomItem,
    randomInteger,
    randomNumber,
} from '../../support.js';

const createdProductIds = [];

export function testProducts() {
    const choice = Math.random() * 100;
    let response;

    if (choice < 25) {
        response = getProducts();
    } else if (choice < 50) {
        response = getProductById();
    } else if (choice < 70) {
        response = getPopularProducts();
    } else if (choice < 90) {
        response = getProductsByCategory();
    } else if (choice < 95) {
        response = createProduct();
    } else {
        response = updateProduct();
    }

    recordResult(response, productTime, 'Products API');
}

function getProducts() {
    const categoryId = randomItem(CATEGORY_IDS);

    return http.get(
        `${BASE_URL}/products?category_id=${categoryId}`,
        requestOptions(
            'products',
            'GET /products?category_id={id}'
        )
    );
}

function getProductById() {
    const productId = randomItem(PRODUCT_IDS);

    return http.get(
        `${BASE_URL}/products/${productId}`,
        requestOptions('products', 'GET /products/{id}')
    );
}

function getPopularProducts() {
    return http.get(
        `${BASE_URL}/products/popular`,
        requestOptions('products', 'GET /products/popular')
    );
}

function getProductsByCategory() {
    const categoryId = randomItem(CATEGORY_IDS);

    return http.get(
        `${BASE_URL}/categories/${categoryId}/products`,
        requestOptions(
            'products',
            'GET /categories/{categoryId}/products'
        )
    );
}

function createProduct() {
    const categoryId = randomItem(CATEGORY_IDS);

    const uniqueValue = `${__VU}-${__ITER}-${Date.now()}`;

    const body = JSON.stringify({
        name: `Load Test Product ${uniqueValue}`,
        description: 'Product created by the k6 load test',
        price: Number(randomNumber(10, 200).toFixed(2)),
        stock: randomInteger(10, 100),
        size: 'L',
        image_url: 'https://example.com/load-test-product.jpg',
        is_active: true,
        category_id: categoryId,
    });

    const response = http.post(
        `${BASE_URL}/products`,
        body,
        requestOptions('products', 'POST /products')
    );

    rememberCreatedProduct(response);

    return response;
}

function updateProduct() {
    const productId = createdProductIds.length > 0
        ? randomItem(createdProductIds)
        : randomItem(PRODUCT_IDS);

    const categoryId = randomItem(CATEGORY_IDS);
    const uniqueValue = `${productId}-${__VU}-${__ITER}-${Date.now()}`;

    const body = JSON.stringify({
        name: `Updated Product ${uniqueValue}`,
        description: 'Product updated by the k6 load test',
        price: Number(randomNumber(20, 250).toFixed(2)),
        stock: randomInteger(20, 150),
        size: 'XL',
        image_url: 'https://example.com/updated-product.jpg',
        is_active: true,
        category_id: categoryId,
    });

    return http.put(
        `${BASE_URL}/products/update/${productId}`,
        body,
        requestOptions(
            'products',
            'PUT /products/update/{id}'
        )
    );
}

function rememberCreatedProduct(response) {
    if (response.status < 200 || response.status >= 300) {
        return;
    }

    try {
        const body = response.json();

        const productId = Number(
            body?.data?.id ??
            body?.product?.id ??
            body?.id
        );

        if (
            Number.isInteger(productId) &&
            productId > 0 &&
            !createdProductIds.includes(productId)
        ) {
            createdProductIds.push(productId);
        }

        if (createdProductIds.length > 100) {
            createdProductIds.shift();
        }
    } catch (_) {
        // Ignore response-shape differences.
    }
}
