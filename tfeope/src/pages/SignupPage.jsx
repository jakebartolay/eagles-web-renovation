import { useEffect, useState } from 'react';
import { navigateTo } from '../components/HashRouter';
import { useAuth } from '../context/AuthContext';

export default function SignupPage() {
  const { signup, loading, error, clearError, user } = useAuth();
  const [form, setForm] = useState({ name: '', username: '', password: '', passwordConfirm: '' });
  const [localError, setLocalError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  useEffect(() => { if (user) navigateTo('/profile'); }, [user]);
  useEffect(() => { clearError(); }, [clearError]);

  const set = (field) => (e) => setForm((prev) => ({ ...prev, [field]: e.target.value }));

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLocalError('');
    setSuccessMsg('');

    const { name, username, password, passwordConfirm } = form;

    if (!name.trim() || !username.trim() || !password) {
      setLocalError('Please fill in all fields.');
      return;
    }
    if (password !== passwordConfirm) {
      setLocalError('Passwords do not match.');
      return;
    }
    if (password.length < 8) {
      setLocalError('Password must be at least 8 characters.');
      return;
    }

    const result = await signup({ name: name.trim(), username: username.trim(), password, passwordConfirm });

    if (result.ok) {
      setSuccessMsg('Account created! Redirecting to sign in…');
      setTimeout(() => navigateTo('/login'), 1800);
    }
  };

  const displayError = localError || error;

  return (
    <div className="auth-page">
      <div className="auth-card">
        <div className="auth-card__header">
          <img src="/logo.png" alt="Eagles logo" className="auth-card__logo" />
          <h1 className="auth-card__title">Create Account</h1>
          <p className="auth-card__subtitle">Sign up first — you'll link your Eagles ID after logging in.</p>
        </div>

        {displayError && <div className="auth-alert auth-alert--error" role="alert">{displayError}</div>}
        {successMsg && <div className="auth-alert auth-alert--success" role="status">{successMsg}</div>}

        <form className="auth-form" onSubmit={handleSubmit} noValidate>
          <div className="auth-form__group">
            <label htmlFor="su-name" className="auth-form__label">Full Name</label>
            <input id="su-name" type="text" className="auth-form__input"
              value={form.name} onChange={set('name')} disabled={loading}
              placeholder="Juan dela Cruz" autoComplete="name" />
          </div>

          <div className="auth-form__group">
            <label htmlFor="su-username" className="auth-form__label">Username</label>
            <input id="su-username" type="text" className="auth-form__input"
              value={form.username} onChange={set('username')} disabled={loading}
              placeholder="4–20 chars, letters/numbers/underscore" autoComplete="username" />
          </div>

          <div className="auth-form__group">
            <label htmlFor="su-password" className="auth-form__label">Password</label>
            <input id="su-password" type="password" className="auth-form__input"
              value={form.password} onChange={set('password')} disabled={loading}
              placeholder="Minimum 8 characters" autoComplete="new-password" />
          </div>

          <div className="auth-form__group">
            <label htmlFor="su-confirm" className="auth-form__label">Confirm Password</label>
            <input id="su-confirm" type="password" className="auth-form__input"
              value={form.passwordConfirm} onChange={set('passwordConfirm')} disabled={loading}
              placeholder="Re-enter password" autoComplete="new-password" />
          </div>

          <button type="submit" className="auth-form__btn" disabled={loading}>
            {loading ? 'Creating account…' : 'Create Account'}
          </button>
        </form>

        <p className="auth-card__footer-text">
          Already have an account?{' '}
          <button className="auth-link" onClick={() => navigateTo('/login')}>Sign in</button>
        </p>
      </div>
    </div>
  );
}
