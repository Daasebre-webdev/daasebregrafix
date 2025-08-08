'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import styles from './results.module.css';
import Link from 'next/link';

interface Project {
  id: string;
  title: string;
  category: string;
  description: string;
  technologies?: string;
}

export default function ResultsPage() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [bookmarks, setBookmarks] = useState<string[]>([]);
  const router = useRouter();

  useEffect(() => {
    const storedProjects = localStorage.getItem('generatedProjects');
    if (storedProjects) {
      try {
        const parsed = JSON.parse(storedProjects);
        setProjects(parsed);
      } catch (err) {
        console.error('Failed to parse stored projects:', err);
      }
    }

    const savedBookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');
    setBookmarks(savedBookmarks);
  }, []);

  const toggleBookmark = (projectId: string) => {
    const newBookmarks = bookmarks.includes(projectId)
      ? bookmarks.filter((id) => id !== projectId)
      : [...bookmarks, projectId];

    setBookmarks(newBookmarks);
    localStorage.setItem('bookmarks', JSON.stringify(newBookmarks));
  };

  return (
    <div className={styles['results-container']}>
      {/* Header Section */}
      <div className={styles['header']}>
        <h1 className={styles['main-title']}>Generated Project Ideas</h1>
        <p className={styles['intro-text']}>
          Here are your personalized project recommendations based on your preferences and requirements. 
          Click on any project to view detailed information and implementation steps.
        </p>
      </div>

      {/* Navigation Menu */}
      <div className={styles['menu']}>
        <Link href="/dashboard" className={styles['menu-link']}>Browse Projects</Link>
        <span className={styles['menu-separator']}>|</span>
        <Link href="/ai" className={styles['menu-link']}>Generate New Project</Link>
        <span className={styles['menu-separator']}>|</span>
        <Link href="/bookmarks" className={styles['menu-link']}>View Bookmarks</Link>
      </div>

      {/* Content Section */}
      <div className={styles['content-wrapper']}>
        {projects.length === 0 ? (
          <div className={styles['empty-state']}>
            <h2>No Projects Generated Yet</h2>
            <p>Start by generating some personalized project ideas based on your preferences.</p>
            <Link href="/ai" className={styles['generate-button']}>
              Generate Projects
            </Link>
          </div>
        ) : (
          <div className={styles['recommendations']}>
            {projects.map((project) => (
              <Link
                key={project.id}
                href={`/projectspage/${project.id}`}
                className={styles['recommendation-link']}
              >
                <div className={styles['recommendation-tab']}>
                  <button
                    onClick={(e) => {
                      e.preventDefault(); // Prevent link navigation
                      toggleBookmark(project.id);
                    }}
                    className={styles.bookmarkBtn}
                    aria-label={bookmarks.includes(project.id) ? 'Remove bookmark' : 'Add bookmark'}
                  >
                    {bookmarks.includes(project.id) ? '★' : '☆'}
                  </button>

                  <div className={styles['recommendation-tab-title']}>{project.title}</div>
                  <div className={styles['recommendation-tab-category']}>
                    {project.category || 'General'}
                  </div>
                  <div className={styles['recommendation-tab-description']}>
                    {project.description}
                  </div>
                  {project.technologies && (
                    <div className={styles['technologies']}>
                      <strong>Technologies:</strong> {project.technologies}
                    </div>
                  )}
                </div>
              </Link>
            ))}
          </div>
        )}

        {/* Action Buttons */}
        <div className={styles['action-buttons']}>
          <button 
            onClick={() => router.push('/ai')} 
            className={styles['back-button']}
          >
            ← Back to Generator
          </button>
          
          {bookmarks.length > 0 && (
            <button 
              onClick={() => router.push('/bookmarks')} 
              className={styles['bookmarks-button']}
            >
              View Bookmarks ({bookmarks.length})
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
