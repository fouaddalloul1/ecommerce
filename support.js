import { check } from 'k6';
import { Rate, Trend } from 'k6/metrics';

export const BASE_URL = 'http://127.0.0.1:8000/api/v1';
export const TOKEN = 'Bearer 1|HdmaOKemJ27gdGBXhtCpEZTzGIzjpAKeflk10IfOc8165952';

export const PRODUCT_IDS = [1, 2, 3, 4, 5];
export const CATEGORY_IDS = [1, 2, 3];
export const ORDER_IDS = [1, 2, 3];

export const productTime = new Trend('product_response_time', true);
export const categoryTime = new Trend('category_response_time', true);
export const orderTime = new Trend('order_response_time', true);
export const apiErrors = new Rate('api_error_rate');

export function requestOptions(moduleName, endpointName) {
    return {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${TOKEN}`,
        },
        tags: {
            module: moduleName,
            endpoint: endpointName,
        },
    };
}

export function recordResult(response, moduleTrend, checkName) {
    const isBusinessRejection =
        response.status === 409 || response.status === 422;

    const failed =
        response.status === 0 ||
        (
            (response.status < 200 || response.status >= 400) &&
            !isBusinessRejection
        );

    moduleTrend.add(response.timings.duration);
    apiErrors.add(failed);

    check(response, {
        [`${checkName} returned a valid response`]: () => !failed,
    });
}

export function randomItem(items) {
    return items[Math.floor(Math.random() * items.length)];
}

export function randomNumber(min, max) {
    return Math.random() * (max - min) + min;
}

export function randomInteger(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}
