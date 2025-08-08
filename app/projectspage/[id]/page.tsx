'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getAllProjects, Project } from '@/app/utils/helpers';
import styles from './projectspage.module.css';


interface PageProps {
  params: { id: string };
}

export default function ProjectPage({ params }: PageProps) {
  const { id } = params;

  const [project, setProject] = useState<Project | null>(null);
  const [isBookmarked, setIsBookmarked] = useState(false);
  const [isMounted, setIsMounted] = useState(false);
  const router = useRouter();

  useEffect(() => {
    setIsMounted(true);
    const allProjects = getAllProjects();
    const foundProject = allProjects.find((p) => p.id === id);

    if (!foundProject) {
      router.push('/404');
      return;
    }

    setProject(foundProject);

    const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');
    setIsBookmarked(bookmarks.includes(id));
  }, [id, router]);

  const toggleBookmark = () => {
    const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');
    const updated = isBookmarked
      ? bookmarks.filter((bId: string) => bId !== id)
      : [...bookmarks, id];
    localStorage.setItem('bookmarks', JSON.stringify(updated));
    setIsBookmarked(!isBookmarked);
  };

  if (!isMounted) return <div className={styles.loading}>Loading...</div>;
  if (!project) return <div className={styles.loading}>Project not found</div>;

  return (
    <div className={styles.projectContainer}>
      <button onClick={() => router.back()} className={styles.backLink}>
        &larr; Back
      </button>

      <h1 className={styles.heading}>{project.title}</h1>
      <span className={styles.category}>{project.category}</span>
      <p className={styles.description}>{project.description}</p>

      <div className={styles.bookmarkControls}>
        <button
          onClick={toggleBookmark}
          className={`${styles.bookmarkButton} ${isBookmarked ? styles.bookmarked : ''}`}
        >
          {isBookmarked ? 'Bookmarked ✓' : 'Bookmark this Project'}
        </button>
      </div>

      {project.details && (
        <div className={styles.detailsSection}>
          <h2 className={styles.subHeading}>Project Details</h2>
          <div className={styles.detailGrid}>
            {project.details.fullDescription && (
              <div className={styles.stepCard}>
                <h3 className={styles.stepTitle}>Description</h3>
                <p>{project.details.fullDescription}</p>
              </div>
            )}
            {project.details.difficulty && (
              <div className={styles.stepCard}>
                <h3 className={styles.stepTitle}>Difficulty</h3>
                <p>{project.details.difficulty}</p>
              </div>
            )}
            {project.details.duration && (
              <div className={styles.stepCard}>
                <h3 className={styles.stepTitle}>Duration</h3>
                <p>{project.details.duration}</p>
              </div>
            )}
            {project.details.learningObjectives && (
              <div className={styles.stepCard}>
                <h3 className={styles.stepTitle}>Learning Objectives</h3>
                <ul className={styles.stepItems}>
                  {project.details.learningObjectives.map((obj, index) => (
                    <li key={index} className={styles.stepItem}>
                      <span className={styles.itemBullet}>•</span> {obj}
                    </li>
                  ))}
                </ul>
              </div>
            )}
            {project.details.steps && (
              <div className={styles.stepCard}>
                <h3 className={styles.stepTitle}>Steps</h3>
                <ul className={styles.stepItems}>
                  {project.details.steps.map((step, index) => (
                    <li key={index} className={styles.stepItem}>
                      <strong>{step.title}</strong>
                      <ul>
                        {step.items.map((item, i) => (
                          <li key={i} className={styles.stepItem}>
                            <span className={styles.itemBullet}>–</span> {item}
                          </li>
                        ))}
                      </ul>
                    </li>
                  ))}
                </ul>
              </div>
            )}
            {project.details.resources && (
              <div className={styles.stepCard}>
                <h3 className={styles.stepTitle}>Resources</h3>
                {project.details.resources.tools && (
                  <>
                    <h4>Tools</h4>
                    <ul className={styles.stepItems}>
                      {project.details.resources.tools.map((tool: any, index: number) => (
                        <li key={index} className={styles.stepItem}>
                          <a href={tool.link} target="_blank" rel="noopener noreferrer">
                            {tool.name}
                          </a>
                        </li>
                      ))}
                    </ul>
                  </>
                )}
                {project.details.resources.guides && (
                  <>
                    <h4>Guides</h4>
                    <ul className={styles.stepItems}>
                      {project.details.resources.guides.map((guide: any, index: number) => (
                        <li key={index} className={styles.stepItem}>
                          <a href={guide.link} target="_blank" rel="noopener noreferrer">
                            {guide.title}
                          </a>
                        </li>
                      ))}
                    </ul>
                  </>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
