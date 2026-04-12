const API_BASE = '/api';
const ACCESS_TOKEN_KEY = 'dochelper_jwt';
const REFRESH_TOKEN_KEY = 'dochelper_refresh_token';

function getStoredAccessToken() {
  return localStorage.getItem(ACCESS_TOKEN_KEY);
}

function getStoredRefreshToken() {
  return localStorage.getItem(REFRESH_TOKEN_KEY);
}

function storeTokens({ token, refreshToken }) {
  localStorage.setItem(ACCESS_TOKEN_KEY, token);
  localStorage.setItem(REFRESH_TOKEN_KEY, refreshToken);
}

export function clearTokens() {
  localStorage.removeItem(ACCESS_TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
}

async function request(path, { method = 'GET', body, token, retryOnAuthFailure = true } = {}) {
  const headers = {
    'Content-Type': 'application/json',
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  const data = await response.json().catch(() => ({}));

  if (response.status === 401 && retryOnAuthFailure) {
    const refreshed = await refreshAccessToken();

    if (refreshed) {
      return request(path, {
        method,
        body,
        token: getStoredAccessToken(),
        retryOnAuthFailure: false,
      });
    }
  }

  if (!response.ok) {
    const message = data.error || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return data;
}

export function registerUser(payload) {
  return request('/register', { method: 'POST', body: payload });
}

export async function loginUser(payload) {
  const data = await request('/login', { method: 'POST', body: payload, retryOnAuthFailure: false });
  storeTokens(data);

  return data;
}

export function getProfile() {
  return request('/me', { token: getStoredAccessToken() });
}

export async function logoutUser() {
  const refreshToken = getStoredRefreshToken();

  if (refreshToken) {
    await request('/logout', {
      method: 'POST',
      body: { refreshToken },
      token: getStoredAccessToken(),
      retryOnAuthFailure: false,
    }).catch(() => null);
  }

  clearTokens();
}

export async function refreshAccessToken() {
  const refreshToken = getStoredRefreshToken();

  if (!refreshToken) {
    return false;
  }

  try {
    const data = await request('/token/refresh', {
      method: 'POST',
      body: { refreshToken },
      retryOnAuthFailure: false,
    });
    storeTokens(data);

    return true;
  } catch {
    clearTokens();

    return false;
  }
}
