'use client';

import { useEffect, useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { getAllProjects, type Project } from '@/app/utils/helpers';
import { useBookmarks } from '@/hooks/useBookmarks';
import styles from './projectspage.module.css';

// Define types for resources
interface ResourceItem {
  name: string;
  link: string;
}

interface GuideItem {
  title: string;
  link: string;
}

interface ProjectResources {
  tools?: ResourceItem[];
  guides?: GuideItem[];
}

// Augment the Project type to include detailed resources
interface DetailedProject extends Project {
  details?: {
    fullDescription?: string;
    difficulty?: string;
    duration?: string;
    learningObjectives?: string[];
    steps?: {
      title: string;
      items: string[];
    }[];
    resources?: ProjectResources;
  };
}

export default function ProjectPage() {
  const params = useParams();
  const rawId = params?.id;

  // Normalize id to always be a string
  const id = Array.isArray(rawId) ? rawId[0] : rawId ?? '';
  const router = useRouter();

  const [project, setProject] = useState<DetailedProject | null>(null);
  const [isMounted, setIsMounted] = useState(false);
  const { isBookmarked, toggleBookmark, isLoading: bookmarksLoading } = useBookmarks();

  useEffect(() => {
    setIsMounted(true);
    const allProjects = getAllProjects();
    const foundProject = allProjects.find((p) => p.id === id) as DetailedProject | undefined;

    if (!foundProject) {
      router.push('/404');
      return;
    }

    setProject(foundProject);
  }, [id, router]);

  const handleBookmarkToggle = () => {
    if (id) toggleBookmark(id);
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
          onClick={handleBookmarkToggle}
          disabled={bookmarksLoading}
          className={`${styles.bookmarkButton} ${
            isBookmarked(id) ? styles.bookmarked : ''
          }`}
        >
          {bookmarksLoading
            ? '...'
            : isBookmarked(id)
            ? 'Bookmarked ✓'
            : 'Bookmark this Project'}
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
                      {project.details.resources.tools.map((tool, index) => (
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
                      {project.details.resources.guides.map((guide, index) => (
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
