import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { API_ENDPOINTS, buildApiUrl } from '../config/api';

const AuthContext = createContext(null);

const AUTH_FETCH_OPTS = {
  credentials: 'include',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
};

async function apiFetch(endpoint, method = 'GET', body = null) {
  const opts = { ...AUTH_FETCH_OPTS, method };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(buildApiUrl(endpoint), opts);
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch {
    throw new Error('Server returned an unexpected response.');
  }
}

export function AuthProvider({ children }) {
  const [user, setUser]       = useState(null);   // { id, name, username, eaglesId, roleId }
  const [ready, setReady]     = useState(false);  // session check done?
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');

  // Check existing session on mount
  useEffect(() => {
    apiFetch(API_ENDPOINTS.auth.session)
      .then((data) => {
        if (data?.authenticated && data?.data) {
          setUser(data.data);
        }
      })
      .catch(() => {})
      .finally(() => setReady(true));
  }, []);

  const login = useCallback(async (username, password) => {
    setLoading(true);
    setError('');
    try {
      const data = await apiFetch(API_ENDPOINTS.auth.login, 'POST', { username, password });
      if (!data?.success) throw new Error(data?.message || 'Login failed.');
      setUser(data.data);
      return { ok: true };
    } catch (err) {
      const msg = err?.message || 'Unable to sign in. Please try again.';
      setError(msg);
      return { ok: false, message: msg };
    } finally {
      setLoading(false);
    }
  }, []);

  const signup = useCallback(async (fields) => {
    setLoading(true);
    setError('');
    try {
      const data = await apiFetch(API_ENDPOINTS.auth.signup, 'POST', fields);
      if (!data?.success) throw new Error(data?.message || 'Signup failed.');
      return { ok: true };
    } catch (err) {
      const msg = err?.message || 'Unable to create account. Please try again.';
      setError(msg);
      return { ok: false, message: msg };
    } finally {
      setLoading(false);
    }
  }, []);

  const logout = useCallback(async () => {
    await apiFetch('/v1/client/auth/logout.php', 'POST').catch(() => {});
    setUser(null);
  }, []);

  const clearError = useCallback(() => setError(''), []);

  return (
    <AuthContext.Provider value={{ user, setUser, ready, loading, error, login, signup, logout, clearError }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
}
