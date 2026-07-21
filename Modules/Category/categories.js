import http from 'k6/http';

import {
    BASE_URL,
    CATEGORY_IDS,
    categoryTime,
    requestOptions,
    recordResult,
    randomItem,
} from '../../support.js';

export function testCategories() {
    const choice = Math.random() * 100;
    let response;

    if (choice < 60) {
        response = getCategories();
    } else {
        response = getCategoryById();
    }

    recordResult(response, categoryTime, 'Categories API');
}

function getCategories() {
    return http.get(
        `${BASE_URL}/categories`,
        requestOptions('categories', 'GET /categories')
    );
}

function getCategoryById() {
    const categoryId = randomItem(CATEGORY_IDS);

    return http.get(
        `${BASE_URL}/categories/${categoryId}`,
        requestOptions('categories', 'GET /categories/{id}')
    );
}
