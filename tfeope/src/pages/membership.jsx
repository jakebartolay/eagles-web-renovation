import { useEffect, useState } from 'react'
import PublicShell from '../components/PublicShell'
import useBodyClass from '../hooks/useBodyClass'
import useStylesheet from '../hooks/useStylesheet'
import { fetchApiJson, postJson } from '../lib/api'
import {
  PUBLIC_AUTH_LOGIN_ENDPOINT,
  PUBLIC_AUTH_SESSION_ENDPOINT,
  PUBLIC_AUTH_SIGNUP_ENDPOINT,
  PUBLIC_MEMBER_VERIFY_ENDPOINT,
} from '../config'
import memberStylesheetUrl from '../theme/member.css?url'

const idTemplateUrl = new URL('../../old_system/static/id_template.png', import.meta.url).href
const certifiedStampUrl = new URL('../../old_system/static/Certified.png', import.meta.url).href
const placeholderPhotoUrl = new URL('../../old_system/static/placeholder.png', import.meta.url).href

function buildVerifyMeta(result) {
  if (!result) {
    return {
      title: '',
      subtitle: '',
    }
  }

  if (result.kind === 'error') {
    return {
      title: result.title,
      subtitle: result.subtitle,
    }
  }

  if (result.statusType === 'active') {
    return {
      title: 'Member Verified',
      subtitle: 'This member is verified and currently active.',
    }
  }

  if (result.statusType === 'renewal') {
    return {
      title: 'Member Verified (For Renewal)',
      subtitle: 'This member is verified but currently marked for renewal.',
    }
  }

  return {
    title: 'Member Verified',
    subtitle: 'This member is a verified record, but the status is not active.',
  }
}

export default function Membership() {
  const [session, setSession] = useState(null)
  const [checkingSession, setCheckingSession] = useState(true)
  const [authModalOpen, setAuthModalOpen] = useState(false)
  const [verifyModalOpen, setVerifyModalOpen] = useState(false)
  const [activeTab, setActiveTab] = useState('signin')
  const [verifyId, setVerifyId] = useState('')
  const [verifyResult, setVerifyResult] = useState(null)
  const [authPrompt, setAuthPrompt] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [signinForm, setSigninForm] = useState({
    username: '',
    password: '',
  })
  const [signupForm, setSignupForm] = useState({
    name: '',
    username: '',
    eaglesId: '',
    password: '',
    passwordConfirm: '',
  })
  const [signinFeedback, setSigninFeedback] = useState(null)
  const [signupFeedback, setSignupFeedback] = useState(null)
  const [submitting, setSubmitting] = useState({
    signin: false,
    signup: false,
    verify: false,
  })

  useStylesheet(memberStylesheetUrl)
  useBodyClass(authModalOpen || verifyModalOpen ? 'modal-open' : '')

  async function loadSession() {
    const payload = await fetchApiJson(PUBLIC_AUTH_SESSION_ENDPOINT, {
      credentials: 'include',
    })

    setSession(payload.authenticated ? payload.data : null)
  }

  useEffect(() => {
    let cancelled = false

    async function bootstrapSession() {
      try {
        await loadSession()
      } catch {
        if (!cancelled) {
          setSession(null)
        }
      } finally {
        if (!cancelled) {
          setCheckingSession(false)
        }
      }
    }

    bootstrapSession()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        setAuthModalOpen(false)
        setVerifyModalOpen(false)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [])

  function openSigninGate(promptMessage = 'Please sign in to continue.') {
    setActiveTab('signin')
    setAuthPrompt(promptMessage)
    setAuthModalOpen(true)
    setSigninFeedback(null)
  }

  function closeAuthModal() {
    setAuthModalOpen(false)
    setAuthPrompt('')
  }

  function closeVerifyModal() {
    setVerifyModalOpen(false)
  }

  async function handleSigninSubmit(event) {
    event.preventDefault()

    try {
      setSubmitting((current) => ({ ...current, signin: true }))
      setSigninFeedback(null)

      await postJson(
        PUBLIC_AUTH_LOGIN_ENDPOINT,
        {
          username: signinForm.username,
          password: signinForm.password,
        },
        {
          credentials: 'include',
        },
      )

      await loadSession()
      setAuthModalOpen(false)
      setAuthPrompt('')
      setSigninForm({
        username: signinForm.username,
        password: '',
      })
      setSigninFeedback({
        type: 'success',
        message: 'Signed in successfully.',
      })
    } catch (error) {
      setSigninFeedback({
        type: 'error',
        message: error.message || 'Unable to sign in right now.',
      })
    } finally {
      setSubmitting((current) => ({ ...current, signin: false }))
    }
  }

  async function handleSignupSubmit(event) {
    event.preventDefault()

    try {
      setSubmitting((current) => ({ ...current, signup: true }))
      setSignupFeedback(null)

      await postJson(
        PUBLIC_AUTH_SIGNUP_ENDPOINT,
        {
          name: signupForm.name,
          username: signupForm.username,
          eaglesId: signupForm.eaglesId,
          password: signupForm.password,
          passwordConfirm: signupForm.passwordConfirm,
        },
        {
          credentials: 'include',
        },
      )

      setSignupFeedback({
        type: 'success',
        message: 'Account created. You can sign in now.',
      })
      setActiveTab('signin')
      setSigninFeedback({
        type: 'success',
        message: 'Account created. You can sign in now.',
      })
      setSignupForm({
        name: '',
        username: '',
        eaglesId: '',
        password: '',
        passwordConfirm: '',
      })
    } catch (error) {
      setSignupFeedback({
        type: 'error',
        message: error.message || 'Unable to create your account right now.',
      })
      setActiveTab('signup')
    } finally {
      setSubmitting((current) => ({ ...current, signup: false }))
    }
  }

  async function handleVerifySubmit(event) {
    event.preventDefault()

    if (!session) {
      openSigninGate('Please sign in to verify membership.')
      return
    }

    try {
      setSubmitting((current) => ({ ...current, verify: true }))

      const payload = await fetchApiJson(
        `${PUBLIC_MEMBER_VERIFY_ENDPOINT}?id=${encodeURIComponent(verifyId.trim().toUpperCase())}`,
        {
          credentials: 'include',
        },
      )

      setVerifyResult({
        kind: 'member',
        ...payload.data,
      })
      setVerifyModalOpen(true)
    } catch (error) {
      setVerifyResult({
        kind: 'error',
        title: error.message === 'ID not found.' ? 'ID Not Found' : 'Verification Unavailable',
        subtitle: error.message === 'ID not found.'
          ? 'No matching record was found. Please double-check the ID.'
          : error.message || 'Unable to verify membership at this time.',
      })
      setVerifyModalOpen(true)
    } finally {
      setSubmitting((current) => ({ ...current, verify: false }))
    }
  }

  const verifyMeta = buildVerifyMeta(verifyResult)

  return (
    <PublicShell>
      <style>{`
        body.modal-open {
          overflow: hidden;
        }

        body.auth-locked {
          overflow: hidden;
        }

        #authBlocker {
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.35);
          backdrop-filter: blur(8px);
          -webkit-backdrop-filter: blur(8px);
          opacity: 0;
          pointer-events: none;
          transition: opacity .18s ease;
          z-index: 9998;
        }

        #authBlocker.active {
          opacity: 1;
          pointer-events: auto;
        }
      `}</style>

      <div id="authBlocker" className={authModalOpen ? 'active' : ''} aria-hidden={authModalOpen ? 'false' : 'true'}></div>

      <section className="page-header">
        <h1>Membership Guide</h1>
        <p>
          This membership guide will help you understand who we are, what we stand for,
          and how you can become part of a strong brotherhood dedicated to service, unity, and integrity.
        </p>
      </section>

      <div className="container-guide layout-modern">
        <div className="guide-left">
          <div className="guide-section">
            <h2><i className="fa-solid fa-user-check"></i> Who Can Join?</h2>
            <p>
              Membership is open to individuals who are willing to follow the values and mission of the Eagles.
              Applicants must demonstrate good moral character and commitment to helping the community.
            </p>
            <ul className="list">
              <li>Respectful and responsible individuals</li>
              <li>Willing to participate in events and community service</li>
              <li>Committed to the Four Pillars of the Eagles</li>
              <li>Ready to support the brotherhood and uphold discipline</li>
            </ul>
            <div className="note">
              <strong>Note:</strong> Each local chapter may have specific requirements such as age, student status, and approval process.
            </div>
          </div>

          <div className="guide-section">
            <h2><i className="fa-solid fa-handshake-angle"></i> Benefits of Becoming an Eagle</h2>
            <p>Joining Ang Agila means being part of a respected organization that supports personal growth and community impact.</p>
            <div className="grid">
              <div className="card"><h3><i className="fa-solid fa-people-group"></i> Brotherhood</h3><p>Build strong bonds, lifelong friendships, and teamwork through activities and gatherings.</p></div>
              <div className="card"><h3><i className="fa-solid fa-heart"></i> Charity &amp; Service</h3><p>Join outreach programs, donation drives, and volunteer work to help communities.</p></div>
              <div className="card"><h3><i className="fa-solid fa-star"></i> Leadership</h3><p>Develop leadership and responsibility through training, committees, and chapter roles.</p></div>
              <div className="card"><h3><i className="fa-solid fa-shield-halved"></i> Discipline</h3><p>Learn values of integrity, respect, and accountability as part of the organization.</p></div>
            </div>
          </div>

          <div className="guide-section">
            <h2><i className="fa-solid fa-scale-balanced"></i> Responsibilities of a Member</h2>
            <p>Membership comes with responsibilities that protect the image of the organization and strengthen the brotherhood.</p>
            <ul className="list">
              <li>Follow the Eagles Code of Conduct and chapter rules</li>
              <li>Participate in meetings, events, and service activities</li>
              <li>Show respect to officers, members, and guests</li>
              <li>Promote peace, unity, and discipline in all situations</li>
              <li>Help represent Ang Agila with pride and professionalism</li>
            </ul>
          </div>

          <div className="guide-section">
            <h2><i className="fa-solid fa-clipboard-list"></i> How to Join (Step-by-Step)</h2>
            <p>Here&apos;s a simple guide to becoming an official member:</p>
            <ol className="steps">
              <li><strong>Get a referral</strong> from an existing member or chapter officer.</li>
              <li><strong>Fill out the membership form</strong> with your personal information and contact details.</li>
              <li><strong>Attend a short orientation</strong> to learn the values, mission, and rules of the Eagles.</li>
              <li><strong>Interview &amp; approval</strong> - the chapter will review and approve your application.</li>
              <li><strong>Pay membership dues</strong> (if required by the chapter).</li>
              <li><strong>Induction</strong> - once approved, you&apos;ll receive your status as an official Eagle member.</li>
            </ol>
            <div className="note">
              <strong>Reminder:</strong> Membership is not just a title - it&apos;s a commitment to serve, grow, and represent the organization honorably.
            </div>
          </div>

          <div className="guide-section">
            <h2><i className="fa-solid fa-calendar-check"></i> What to Expect After Joining</h2>
            <p>After becoming a member, you will experience:</p>
            <ul className="list">
              <li>Regular chapter meetings and planning sessions</li>
              <li>Training and leadership development activities</li>
              <li>Community outreach and charity missions</li>
              <li>Brotherhood bonding events and gatherings</li>
              <li>Opportunities to earn recognition and roles</li>
            </ul>
          </div>
        </div>

        <aside className="guide-right">
          <div className="verify-card" id="verifyCard">
            <h2><i className="fa-solid fa-magnifying-glass"></i> Verify Membership</h2>
            <p>
              {session
                ? 'Enter your Membership ID to check if you are a registered Eagle member.'
                : checkingSession
                  ? 'Checking sign-in status...'
                  : 'Sign in first to verify if a Membership ID belongs to a registered Eagle member.'}
            </p>

            <form className="modern-search" id="verifyForm" onSubmit={handleVerifySubmit}>
              <input
                type="text"
                id="verifyInput"
                value={verifyId}
                onChange={(event) => setVerifyId(event.target.value.toUpperCase())}
                required
                autoComplete="off"
                placeholder="TFOEPE00000000"
              />
              <button type="submit" id="verifyBtn" disabled={checkingSession || submitting.verify}>
                {submitting.verify ? 'Verifying...' : 'Verify'}
              </button>
            </form>
          </div>
        </aside>
      </div>

      <div id="verifyModal" className={`verify-modal ${verifyModalOpen ? 'active' : ''}`}>
        <div className="verify-modal-card">
          <button className="verify-close" id="closeVerifyModal" type="button" onClick={closeVerifyModal}>
            <i className="fa-solid fa-xmark"></i>
          </button>

          <div id="verifyModalContent">
            <div className="verify-modal-title">{verifyMeta.title}</div>
            <div className="verify-modal-sub">{verifyMeta.subtitle}</div>

            {verifyResult?.kind === 'member' ? (
              <>
                <div className={`status-pill status-${verifyResult.statusType}`}>
                  {verifyResult.statusLabel}
                </div>

                <div className={`id-shell status-${verifyResult.statusType}`}>
                  <div className={`id-card status-${verifyResult.statusType}`}>
                    <img src={idTemplateUrl} className="id-bg" alt="ID Template" />
                    <div className="id-number">{verifyResult.id}</div>
                    <div className="id-last">{verifyResult.lastName}</div>
                    <div className="id-first">{verifyResult.firstName}</div>
                    <img
                      src={verifyResult.picUrl || placeholderPhotoUrl}
                      className="id-photo"
                      alt={verifyResult.fullName}
                      onError={(event) => {
                        event.currentTarget.src = placeholderPhotoUrl
                      }}
                    />

                    <div className="id-info">
                      <div className="id-club">{verifyResult.club}</div>
                      <div className="id-position">{verifyResult.position}</div>
                      <div className="id-region">{verifyResult.region}</div>
                    </div>
                  </div>

                  {verifyResult.showCertifiedStamp ? (
                    <img src={certifiedStampUrl} className="id-stamp-img" alt="Certified" />
                  ) : null}
                </div>
              </>
            ) : null}
          </div>
        </div>
      </div>

      <div id="loginModal" className={`login-modal ${authModalOpen ? 'active' : ''}`}>
        <div className="login-modal-card">
          <button className="login-close" id="closeLoginModal" type="button" onClick={closeAuthModal}>
            <i className="fa-solid fa-xmark"></i>
          </button>

          <div className="login-tabs">
            <button
              type="button"
              className={`login-tab ${activeTab === 'signin' ? 'active' : ''}`}
              data-tab="signin"
              onClick={() => setActiveTab('signin')}
            >
              Sign In
            </button>
            <button
              type="button"
              className={`login-tab ${activeTab === 'signup' ? 'active' : ''}`}
              data-tab="signup"
              onClick={() => setActiveTab('signup')}
            >
              Create Account
            </button>
          </div>

          <div className={`login-panel ${activeTab === 'signin' ? 'active' : ''}`} id="signin">
            <h2 className="login-title">Welcome Back</h2>
            <p className="login-sub">{authPrompt || 'Sign in to continue.'}</p>

            <form className="login-form" autoComplete="on" onSubmit={handleSigninSubmit}>
              <label htmlFor="loginUsername">Username</label>
              <input
                id="loginUsername"
                type="text"
                name="login_username"
                required
                placeholder="e.g JDcruz27"
                value={signinForm.username}
                onChange={(event) => setSigninForm((current) => ({ ...current, username: event.target.value }))}
              />

              <label htmlFor="loginPassword">Password</label>
              <div className="password-wrap">
                <input
                  type={showPassword ? 'text' : 'password'}
                  name="login_password"
                  id="loginPassword"
                  required
                  placeholder="Enter your password"
                  value={signinForm.password}
                  onChange={(event) => setSigninForm((current) => ({ ...current, password: event.target.value }))}
                />
                <button
                  type="button"
                  className="toggle-pass"
                  id="toggleLoginPass"
                  aria-label="Toggle password"
                  onClick={() => setShowPassword((current) => !current)}
                >
                  <i className={`fa-solid ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i>
                </button>
              </div>

              <button type="submit" className="login-submit" disabled={submitting.signin}>
                {submitting.signin ? 'Signing In...' : 'Sign In'}
              </button>

              {signinFeedback ? (
                <div className={`login-alert ${signinFeedback.type}`}>
                  {signinFeedback.message}
                </div>
              ) : null}
            </form>
          </div>

          <div className={`login-panel ${activeTab === 'signup' ? 'active' : ''}`} id="signup">
            <h2 className="login-title">Create Account</h2>
            <p className="login-sub">Join the Eagles portal.</p>

            <form className="login-form" autoComplete="on" onSubmit={handleSignupSubmit}>
              <label htmlFor="signupName">Full Name</label>
              <input
                id="signupName"
                type="text"
                name="signup_name"
                required
                placeholder="e.g Juan Dela Cruz"
                value={signupForm.name}
                onChange={(event) => setSignupForm((current) => ({ ...current, name: event.target.value }))}
              />

              <label htmlFor="signupUsername">Username</label>
              <input
                id="signupUsername"
                type="text"
                name="signup_username"
                required
                placeholder="e.g JDcruz27"
                value={signupForm.username}
                onChange={(event) => setSignupForm((current) => ({ ...current, username: event.target.value }))}
              />

              <label htmlFor="signupEaglesId">Eagles ID</label>
              <input
                id="signupEaglesId"
                type="text"
                name="signup_eagles_id"
                required
                autoComplete="off"
                placeholder="TFOEPE00000000"
                value={signupForm.eaglesId}
                onChange={(event) => setSignupForm((current) => ({ ...current, eaglesId: event.target.value.toUpperCase() }))}
              />

              <label htmlFor="signupPassword">Password</label>
              <input
                id="signupPassword"
                type="password"
                name="signup_password"
                required
                placeholder="Minimum 8 characters"
                value={signupForm.password}
                onChange={(event) => setSignupForm((current) => ({ ...current, password: event.target.value }))}
              />

              <label htmlFor="signupPasswordConfirm">Confirm Password</label>
              <input
                id="signupPasswordConfirm"
                type="password"
                name="signup_password_confirm"
                required
                placeholder="Re-enter your password"
                value={signupForm.passwordConfirm}
                onChange={(event) => setSignupForm((current) => ({ ...current, passwordConfirm: event.target.value }))}
              />

              <button type="submit" className="login-submit" disabled={submitting.signup}>
                {submitting.signup ? 'Creating Account...' : 'Create Account'}
              </button>

              {signupFeedback ? (
                <div className={`login-alert ${signupFeedback.type}`}>
                  {signupFeedback.message}
                </div>
              ) : null}
            </form>
          </div>
        </div>
      </div>
    </PublicShell>
  )
}
