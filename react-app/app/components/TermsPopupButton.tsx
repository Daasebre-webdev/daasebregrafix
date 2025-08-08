'use client'

import React from 'react'
import Swal, { SweetAlertResult } from 'sweetalert2'
import withReactContent from 'sweetalert2-react-content'

const MySwal = withReactContent(Swal)

const termsText = (
  <div style={{ textAlign: 'left', fontSize: '16px', lineHeight: '1.6' }}>
    <p>
      Welcome to <strong>Project Pulse</strong>! By using our platform, you agree to the following terms:
    </p>
    <ul>
      <li>✅ Use the platform for educational and personal project planning only.</li>
      <li>🔐 Do not share your account credentials with others.</li>
      <li>📄 Respect intellectual property of project ideas and resources.</li>
      <li>🚫 Do not use the platform for unlawful or harmful activities.</li>
    </ul>
    <p>We may update these terms at any time. Continued use means you accept any changes.</p>
  </div>
)

export default function TermsPopupButton() {
  const handleClick = () => {
    MySwal.fire({
      title: 'Terms of Service',
      html: termsText,
      icon: 'info',
      confirmButtonText: 'View Full Terms',
      showCancelButton: true,
      cancelButtonText: 'Close',
      width: 700,
      customClass: {
        popup: 'terms-modal'
      }
    }).then((result: SweetAlertResult) => {
      if (result.isConfirmed) {
        window.location.href = '/terms'
      }
    })
  }

  return (
    <button
      onClick={handleClick}
      style={{
        border: 'none',
        background: 'none',
        color: '#0070f3',
        cursor: 'pointer',
        fontSize: '14px',
        textDecoration: 'underline'
      }}
    >
      View Terms & Conditions
    </button>
  )
}
