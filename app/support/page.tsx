import React from 'react';

export default function Support() {
  return (
    <div style={{ maxWidth: 700, margin: '40px auto', padding: '2rem', background: '#fff', borderRadius: 12, boxShadow: '0 2px 12px rgba(0,0,0,0.07)' }}>
      <h1 style={{ fontSize: '2rem', fontWeight: 700, marginBottom: 16 }}>Support</h1>
      <p>If you need help or have questions about Project Pulse, we&apos;re here for you!</p>
      <h2 style={{ fontSize: '1.2rem', marginTop: 24 }}>Contact Us</h2>
      <ul>
        <li>Email: <a href="mailto:support@projectpulse.com">support@projectpulse.com</a></li>
        <li>FAQ: Coming soon</li>
      </ul>
      <p style={{ marginTop: 24 }}>We aim to respond to all inquiries within 24 hours.</p>
    </div>
  );
}