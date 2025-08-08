
'use client'
import Link from 'next/link';
import styles from './home.module.css';
import { useEffect } from 'react';

export default function Home() {
  // Handle token from PHP Google login
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const url = new URL(window.location.href);
      const token = url.searchParams.get('token');
      if (token) {
        localStorage.setItem('authToken', token);
        // Optionally, redirect to dashboard after login
        window.location.href = '/dashboard';
      }
    }
  }, []);

  const googleLoginUrl = 'http://localhost/Google_signup/index.php';

  return (
    <>
      <div className={styles.home_container}>
        <div className={styles["background-image-container"]}>
          <div className={styles["main-text"]}>
            <div className={styles["heading"]}>
              <h1>Unlock Your Final Year<br />Project Potential</h1> 
            </div>
            <div className={styles["description"]}>
              <p>Get personalized project topic recommendations tailored to your interests and skills. Start your journey to academic success with Project Pulse.</p>
            </div>
            <div className={styles["explore-btn"]}>
              <button
                className={styles["explore-button"]}
                onClick={() => { window.location.href = googleLoginUrl; }}
              >
                Sign in with Google
              </button>
            </div>
          </div>
        </div>

        <div className={styles["tabs-container"]}>
          <div className={styles["left-section"]}>
            <div className={styles["search-section"]}>
              <div className={styles["search-container"]}>
                <svg className={styles["search-icon"]} width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="11" cy="11" r="8" stroke="currentColor" strokeWidth="2"/>
                  <path d="m21 21-4.35-4.35" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
                <input 
                  type="text" 
                  placeholder="Search for project topics..." 
                  className={styles["search-input"]}
                />
              </div>
            </div>
            <div className={styles["featured-projects"]}>
              <h2>Featured Projects</h2>
              <p>Discover trending project ideas and innovative solutions</p>
            </div>
            <div className={styles["left-tabs"]}>
              <div className={styles["tab"]}>
              <div className={styles["tab-header"]}>
                <div className={styles["tab-icon"]}>
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 19.5C4 18.1193 5.11929 17 6.5 17H20" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d="M6.5 2H20V22H6.5C5.11929 22 4 20.8807 4 19.5V2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d="M6.5 2C5.11929 2 4 3.11929 4 4.5V19.5C4 20.8807 5.11929 22 6.5 22" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </div>
              </div>
              <div className={styles["tab-content"]}>
                <h3>AI-Powered Personalized Learning Platform</h3>
                <p>Develop an AI platform that adapts to individual learning styles, providing personalized content and feedback.</p>
                <div className={styles["tab-category"]}>
                  <span>Technology</span>
                </div>
              </div>
            </div>

            <div className={styles["tab"]}>
              <div className={styles["tab-header"]}>
                <div className={styles["tab-icon"]}>
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 19.5C4 18.1193 5.11929 17 6.5 17H20" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d="M6.5 2H20V22H6.5C5.11929 22 4 20.8807 4 19.5V2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d="M6.5 2C5.11929 2 4 3.11929 4 4.5V19.5C4 20.8807 5.11929 22 6.5 22" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </div>
              </div>
              <div className={styles["tab-content"]}>
                <h3>Sustainable Energy Solutions for Urban Areas</h3>
                <p>Design and implement sustainable energy solutions for urban environments, focusing on renewable sources and energy efficiency.</p>
                <div className={styles["tab-category"]}>
                  <span>Environmental Science</span>
                </div>
              </div>
            </div>

            <div className={styles["tab"]}>
              <div className={styles["tab-header"]}>
                <div className={styles["tab-icon"]}>
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 19.5C4 18.1193 5.11929 17 6.5 17H20" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d="M6.5 2H20V22H6.5C5.11929 22 4 20.8807 4 19.5V2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d="M6.5 2C5.11929 2 4 3.11929 4 4.5V19.5C4 20.8807 5.11929 22 6.5 22" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </div>
              </div>
              <div className={styles["tab-content"]}>
                <h3>Innovative Healthcare Technologies</h3>
                <p>Explore and create innovative healthcare technologies, such as wearable devices or telemedicine platforms, to improve patient care.</p>
                <div className={styles["tab-category"]}>
                  <span>Healthcare</span>
                </div>
              </div>
            </div>
          </div>
          </div>

          <div className={styles["right-tabs"]}>
            <div className={styles["side-tab"]}>
              <h3>Related Topics</h3>
              <div className={styles["topic-item"]}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
                <span>AI in Education</span>
              </div>
              <div className={styles["topic-item"]}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
                <span>Personalized Learning Systems</span>
              </div>
              <div className={styles["topic-item"]}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
                <span>Machine Learning Applications</span>
              </div>
            </div>

            <div className={styles["side-tab"]}>
              <h3>Q&A</h3>
              <div className={styles["qa-item"]}>
                <div className={styles["qa-header"]}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2"/>
                    <path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                    <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                    <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                  </svg>
                  <span className={styles["qa-author"]}>Sophia Carter</span>
                  <span className={styles["qa-time"]}>2h</span>
                </div>
                <p>This project looks very promising! I'm excited to see how it can help students.</p>
              </div>
              <div className={styles["qa-item"]}>
                <div className={styles["qa-header"]}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2"/>
                    <path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                    <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                    <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                  </svg>
                  <span className={styles["qa-author"]}>Ethan Bennett</span>
                  <span className={styles["qa-time"]}>4h</span>
                </div>
                <p>Are there any specific prerequisites for this project?</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <footer className={styles.footer}>
        <div className={styles["footer-content"]}>
          <div className={styles["footer-links"]}>
            <Link href="/support" className={styles["footer-link"]}>Support</Link>
            <Link href="/terms" className={styles["footer-link"]}>Terms of Service</Link>
            <Link href="/privacy" className={styles["footer-link"]}>Privacy Policy</Link>
          </div>
          <div className={styles["footer-copyright"]}>
            © 2025 Project Pulse. All rights reserved.
          </div>
        </div>
      </footer>
    </>
  );
}