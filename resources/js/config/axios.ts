import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from 'axios';

/**
 * =========================
 * AXIOS INSTANCE
 * =========================
 */
const api: AxiosInstance = axios.create({
    baseURL: window.location.origin,
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/**
 * =========================
 * CSRF TOKEN (INIT)
 * =========================
 */
const getCsrfFromMeta = () => {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
};

api.defaults.headers.common['X-CSRF-TOKEN'] = getCsrfFromMeta();

/**
 * =========================
 * REFRESH CSRF
 * =========================
 */
const refreshCsrf = async (): Promise<string> => {
    const res = await fetch('/csrf-token', {
        credentials: 'include',
    });

    const data = await res.json();

    const newToken = data.csrf_token;

    // update global header
    api.defaults.headers.common['X-CSRF-TOKEN'] = newToken;

    return newToken;
};

/**
 * =========================
 * QUEUE SYSTEM (tránh spam)
 * =========================
 */
let isRefreshing = false;
let queue: Array<() => void> = [];

const processQueue = () => {
    queue.forEach((cb) => cb());
    queue = [];
};

/**
 * =========================
 * RESPONSE INTERCEPTOR
 * =========================
 */
api.interceptors.response.use(
    (response) => response,

    async (error: AxiosError) => {
        const originalRequest = error.config as InternalAxiosRequestConfig & {
            _retry?: boolean;
        };

        // nếu không phải 419 → bỏ qua
        if (error.response?.status !== 419) {
            return Promise.reject(error);
        }

        // tránh loop vô hạn
        if (originalRequest._retry) {
            return Promise.reject(error);
        }

        originalRequest._retry = true;

        // nếu đang refresh → đợi
        if (isRefreshing) {
            return new Promise((resolve) => {
                queue.push(() => {
                    resolve(api(originalRequest));
                });
            });
        }

        isRefreshing = true;

        try {
            const newToken = await refreshCsrf();

            // gắn lại token cho request cũ
            originalRequest.headers['X-CSRF-TOKEN'] = newToken;

            processQueue();

            return api(originalRequest); // retry
        } catch (err) {
            console.error('Refresh CSRF thất bại');

            // session chết → reload
            window.location.reload();

            return Promise.reject(err);
        } finally {
            isRefreshing = false;
        }
    },
);

export default api;
