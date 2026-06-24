import { useEffect, useState } from 'react';
import { navigateTo } from '../components/HashRouter';
import { useAuth } from '../context/AuthContext';

export default function LoginPage() {
  const { login, loading, error, clearError, user } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [localError, setLocalError] = useState('');

  // Already logged in — bounce to profile
  useEffect(() => {
    if (user) navigateTo('/profile');
  }, [user]);

  useEffect(() => {
    clearError();
  }, [clearError]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLocalError('');

    if (!username.trim() || !password) {
      setLocalError('Please enter your username and password.');
      return;
    }

    const result = await login(username.trim(), password);
    if (result.ok) {
      navigateTo('/profile');
    }
  };

  const displayError = localError || error;

  return (
    <div className="auth-page">
      <div className="auth-card">
        <div className="auth-card__header">
          <img src="/logo.png" alt="Eagles logo" className="auth-card__logo" />
          <h1 className="auth-card__title">Member Sign In</h1>
          <p className="auth-card__subtitle">Fraternal Order of Eagles — Philippines</p>
        </div>

        {displayError && (
          <div className="auth-alert auth-alert--error" role="alert">
            {displayError}
          </div>
        )}

        <form className="auth-form" onSubmit={handleSubmit} noValidate>
          <div className="auth-form__group">
            <label htmlFor="login-username" className="auth-form__label">Username</label>
            <input
              id="login-username"
              type="text"
              className="auth-form__input"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              autoComplete="username"
              disabled={loading}
              placeholder="Enter your username"
            />
          </div>

          <div className="auth-form__group">
            <label htmlFor="login-password" className="auth-form__label">Password</label>
            <input
              id="login-password"
              type="password"
              className="auth-form__input"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              disabled={loading}
              placeholder="Enter your password"
            />
          </div>

          <button type="submit" className="auth-form__btn" disabled={loading}>
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
        </form>

        <p className="auth-card__footer-text">
          No account yet?{' '}
          <button className="auth-link" onClick={() => navigateTo('/signup')}>
            Create one
          </button>
        </p>
      </div>
    </div>
  );
}
