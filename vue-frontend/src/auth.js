import { reactive } from 'vue';
import { api, getAccessToken, setAccessToken } from './api.js';
import { readJson, removeStored, writeJson } from './storage.js';
import { validUser } from './state-utils.js';

const savedUser = readJson('zaimedia_user', null, validUser);
if (!savedUser) setAccessToken(null);

export const auth = reactive({
  user: savedUser,
  token: getAccessToken(),
  get isLoggedIn() {
    return Boolean(this.token && this.user);
  },
  async login(payload) {
    const { data } = await api.post('/auth/login', payload);
    this.setSession(data);
    return data.user;
  },
  async register(payload) {
    const { data } = await api.post('/auth/register', payload);
    this.setSession(data);
    return data.user;
  },
  async logout() {
    try {
      await api.post('/auth/logout');
    } catch {
      // Local logout must also work when the API cannot be reached.
    } finally {
      this.user = null;
      this.token = null;
      removeStored('zaimedia_user');
      setAccessToken(null);
    }
  },
  setSession(data) {
    if (!validUser(data.user) || typeof data.token !== 'string' || !data.token) {
      throw new Error('Respons sesi tidak valid.');
    }
    this.user = data.user;
    this.token = data.token;
    writeJson('zaimedia_user', data.user);
    setAccessToken(data.token);
  },
  async refreshMe() {
    if (!this.token) return null;
    const token = this.token;
    const { data } = await api.get('/auth/me');
    if (this.token !== token) return null;
    if (!validUser(data)) throw new Error('Respons akun tidak valid.');
    this.user = data;
    writeJson('zaimedia_user', data);
    return data;
  },
});

window.addEventListener('zaimedia:unauthorized', () => {
  auth.user = null;
  auth.token = null;
});
