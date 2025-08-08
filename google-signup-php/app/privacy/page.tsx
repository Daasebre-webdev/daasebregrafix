import React from 'react';

export default function PrivacyPolicy() {
  return (
    <div style={{ maxWidth: 700, margin: '40px auto', padding: '2rem', background: '#fff', borderRadius: 12, boxShadow: '0 2px 12px rgba(0,0,0,0.07)' }}>
      <h1 style={{ fontSize: '2rem', fontWeight: 700, marginBottom: 16 }}>Privacy Policy</h1>
      <p>At Project Pulse, your privacy is important to us. We only collect the information necessary to provide personalized project recommendations and improve your experience. We do not sell or share your data with third parties.</p>
      <h2 style={{ fontSize: '1.2rem', marginTop: 24 }}>What We Collect</h2>
      <ul>
        <li>Your name and email address (for login and personalization)</li>
        <li>Profile and academic interests (to tailor recommendations)</li>
        <li>Usage data (to improve our services)</li>
      </ul>
      <h2 style={{ fontSize: '1.2rem', marginTop: 24 }}>How We Use Your Data</h2>
      <ul>
        <li>To provide personalized project suggestions</li>
        <li>To improve the platform and user experience</li>
        <li>To communicate important updates</li>
      </ul>
      <p style={{ marginTop: 24 }}>If you have any questions about your privacy, please contact us at <a href="mailto:support@projectpulse.com">support@projectpulse.com</a>.</p>
    </div>
  );
}
