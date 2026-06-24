import { CheckCircle2, ClipboardList, CreditCard, FileText, Loader2, UploadCloud } from 'lucide-react';
import { useState } from 'react';

const STEPS = [
  { label: 'Information', icon: FileText },
  { label: 'Payment', icon: CreditCard },
  { label: 'Documents', icon: UploadCloud },
  { label: 'Review', icon: ClipboardList },
];

const INITIAL_FORM = {
  fullName: '',
  eaglesId: '',
  chapter: '',
  region: '',
  email: '',
  phone: '',
  paymentMethod: 'gcash',
  referenceNumber: '',
};

export default function MemberApplicationPage() {
  const [form, setForm] = useState(INITIAL_FORM);
  const [submitted, setSubmitted] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const submitApplication = (event) => {
    event.preventDefault();
    setError('');

    const requiredFields = ['fullName', 'eaglesId', 'chapter', 'region', 'email', 'phone', 'referenceNumber'];
    const missing = requiredFields.some((field) => !form[field].trim());
    if (missing) {
      setError('Please complete all required fields before submitting.');
      return;
    }

    setSubmitting(true);
    window.setTimeout(() => {
      setSubmitting(false);
      setSubmitted(true);
    }, 450);
  };

  if (submitted) {
    return (
      <section className="membership-application-page">
        <div className="application-success">
          <CheckCircle2 className="application-success__icon" size={54} />
          <p className="application-kicker">Application Received</p>
          <h2>We have saved your ID application draft.</h2>
          <p>Our team will review your details and contact you if more information is needed.</p>
          <div className="application-success__meta">
            <span>Applicant</span>
            <strong>{form.fullName}</strong>
            <span>Eagles ID</span>
            <strong>{form.eaglesId}</strong>
          </div>
          <button type="button" className="application-btn application-btn--primary" onClick={() => setSubmitted(false)}>
            Review Application
          </button>
        </div>
      </section>
    );
  }

  return (
    <section className="membership-application-page">
      <div className="application-hero">
        <div className="application-hero__inner">
          <p className="application-kicker">TFOE-PE Member Services</p>
          <h1>Membership ID Application</h1>
          <p>Submit your member details, payment reference, and document checklist for ID processing.</p>
        </div>
      </div>

      <div className="application-workspace">
        <aside className="application-steps" aria-label="Application steps">
          {STEPS.map(({ label, icon: Icon }, index) => (
            <div key={label} className={`application-step ${index === 0 ? 'is-active' : ''}`}>
              <span><Icon size={16} /></span>
              <strong>{label}</strong>
              <i>{index + 1}</i>
            </div>
          ))}
        </aside>

        <form className="application-form-panel" onSubmit={submitApplication}>
          <header className="application-form-head">
            <ClipboardList size={22} />
            <div>
              <h2>Application Details</h2>
              <p className="application-kicker">Fields marked with * are required.</p>
            </div>
          </header>

          {error ? <div className="application-error">{error}</div> : null}

          <section className="application-section">
            <div className="application-row">
              <label className="application-field">
                <span>Full Name <b className="application-required">*</b></span>
                <input
                  type="text"
                  value={form.fullName}
                  onChange={(event) => updateField('fullName', event.target.value)}
                  placeholder="Juan Dela Cruz"
                />
              </label>
              <label className="application-field">
                <span>Eagles ID <b className="application-required">*</b></span>
                <input
                  type="text"
                  value={form.eaglesId}
                  onChange={(event) => updateField('eaglesId', event.target.value.toUpperCase())}
                  placeholder="TFOEPE00000000"
                />
              </label>
            </div>

            <div className="application-row">
              <label className="application-field">
                <span>Chapter <b className="application-required">*</b></span>
                <input
                  type="text"
                  value={form.chapter}
                  onChange={(event) => updateField('chapter', event.target.value)}
                  placeholder="Chapter name"
                />
              </label>
              <label className="application-field">
                <span>Region <b className="application-required">*</b></span>
                <input
                  type="text"
                  value={form.region}
                  onChange={(event) => updateField('region', event.target.value)}
                  placeholder="Region"
                />
              </label>
            </div>

            <div className="application-row">
              <label className="application-field">
                <span>Email <b className="application-required">*</b></span>
                <input
                  type="email"
                  value={form.email}
                  onChange={(event) => updateField('email', event.target.value)}
                  placeholder="name@example.com"
                />
              </label>
              <label className="application-field">
                <span>Phone <b className="application-required">*</b></span>
                <input
                  type="tel"
                  value={form.phone}
                  onChange={(event) => updateField('phone', event.target.value)}
                  placeholder="09XXXXXXXXX"
                />
              </label>
            </div>
          </section>

          <section className="application-section">
            <div className="application-payment-options">
              {['gcash', 'bank', 'cash'].map((method) => (
                <button
                  type="button"
                  key={method}
                  className={`application-payment-option ${form.paymentMethod === method ? 'is-selected' : ''}`}
                  onClick={() => updateField('paymentMethod', method)}
                >
                  <strong>{method === 'gcash' ? 'GCash' : method === 'bank' ? 'Bank Transfer' : 'Cash Payment'}</strong>
                  <span>{method === 'cash' ? 'Chapter office payment' : 'Attach your payment reference'}</span>
                </button>
              ))}
            </div>
            <label className="application-field application-row--single">
              <span>Payment Reference <b className="application-required">*</b></span>
              <input
                type="text"
                value={form.referenceNumber}
                onChange={(event) => updateField('referenceNumber', event.target.value)}
                placeholder="Reference number"
              />
            </label>
          </section>

          <section className="application-section">
            <div className="application-upload-field">
              <label>Document Checklist</label>
              <div className="application-upload">
                <UploadCloud className="application-upload__icon" size={34} />
                <div className="application-upload__text">
                  <strong>Prepare your photo and proof of payment.</strong>
                  <small>Upload processing can be connected once the backend endpoint is ready.</small>
                </div>
              </div>
            </div>
          </section>

          <div className="application-actions">
            <button type="button" className="application-btn application-btn--secondary" onClick={() => setForm(INITIAL_FORM)}>
              Clear
            </button>
            <button type="submit" className="application-btn application-btn--primary" disabled={submitting}>
              {submitting ? <Loader2 className="application-spin" size={16} /> : null}
              {submitting ? 'Submitting...' : 'Submit Application'}
            </button>
          </div>
        </form>
      </div>
    </section>
  );
}
