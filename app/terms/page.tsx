import React from 'react';

export default function TermsOfService() {
  return (
    <div style={{ maxWidth: 700, margin: '40px auto', padding: '2rem', background: '#fff', borderRadius: 12, boxShadow: '0 2px 12px rgba(0,0,0,0.07)' }}>
      <h1 style={{ fontSize: '2rem', fontWeight: 700, marginBottom: 16 }}>Terms of Service</h1>
      <p>Welcome to Project Pulse! By using our platform, you agree to the following terms:</p>
      <ul>
        <li>Use the platform for educational and personal project planning purposes only.</li>
        <li>Do not share your account credentials with others.</li>
        <li>Respect the intellectual property of project ideas and resources provided.</li>
        <li>Do not use the platform for any unlawful or harmful activities.</li>
      </ul>
      <p style={{ marginTop: 24 }}>We reserve the right to update these terms at any time. Continued use of Project Pulse means you accept any changes.</p>
    </div>
  );
}
