import axios from 'axios';
import { readText, removeStored, writeText } from './storage.js';

let accessToken = readText('zaimedia_token');

export function getAccessToken() {
  return accessToken;
}

export function setAccessToken(token) {
  accessToken = typeof token === 'string' && token ? token : null;
  if (accessToken) writeText('zaimedia_token', accessToken);
  else removeStored('zaimedia_token');
}

export const api = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = getAccessToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const inactiveAccount = error.response?.status === 403 && error.response?.data?.message === 'Akun tidak aktif';
    if (error.response?.status === 401 || inactiveAccount) {
      const hadSession = Boolean(accessToken);
      setAccessToken(null);
      removeStored('zaimedia_user');
      if (hadSession) window.dispatchEvent(new CustomEvent('zaimedia:unauthorized'));
    }
    return Promise.reject(error);
  },
);

export function money(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}
