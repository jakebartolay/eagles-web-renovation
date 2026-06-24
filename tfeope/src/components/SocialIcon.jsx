export default function SocialIcon({ network, size = 20 }) {
  if (network === 'instagram') {
    return (
      <svg viewBox="0 0 24 24" width={size} height={size} fill="none" aria-hidden="true">
        <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" strokeWidth="2" />
        <circle cx="12" cy="12" r="4" stroke="currentColor" strokeWidth="2" />
        <circle cx="17.4" cy="6.7" r="1.1" fill="currentColor" />
      </svg>
    );
  }

  if (network === 'facebook') {
    return (
      <svg viewBox="0 0 24 24" width={size} height={size} aria-hidden="true">
        <path
          fill="currentColor"
          d="M13.5 22v-8h2.7l.4-3h-3.1V9.1c0-.9.3-1.5 1.6-1.5h1.7V5c-.3 0-1.4-.1-2.6-.1-2.6 0-4.3 1.6-4.3 4.5V11H7v3h2.9v8h3.6Z"
        />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 24 24" width={size} height={size} aria-hidden="true">
      <path
        fill="currentColor"
        d="M21.6 8.2a2.7 2.7 0 0 0-1.9-1.9C18 5.8 12 5.8 12 5.8s-6 0-7.7.5a2.7 2.7 0 0 0-1.9 1.9A28.4 28.4 0 0 0 1.9 12c0 1.3.2 2.5.5 3.8a2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.7.5 7.7.5s6 0 7.7-.5a2.7 2.7 0 0 0 1.9-1.9c.3-1.3.5-2.5.5-3.8s-.2-2.5-.5-3.8ZM10 15.2V8.8l5.5 3.2L10 15.2Z"
      />
    </svg>
  );
}
