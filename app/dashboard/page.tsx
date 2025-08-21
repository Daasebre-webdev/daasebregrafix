'use client'
import { useState } from 'react'
import Image from 'next/image'
import { useUser } from '../context/UserContext'
import styles from './dashboard.module.css'
import Link from 'next/link'
import projects from '../data/projects.json'
import RequireAuth from '../components/RequireAuth'

interface Project {
  id: string;
  title: string;
  description: string;
  category: string;
  details?: {
    difficulty?: string;
    duration?: string;
    fullDescription?: string;
    learningObjectives?: string[];
    steps?: Array<{
      title: string;
      items: string[];
    }>;
    resources?: {
      tools?: Array<{ name: string; link: string }>;
      guides?: Array<{ title: string; link: string }>;
    };
  };
}

export default function Dashboard() {
  const [searchTerm, setSearchTerm] = useState('')
  const { user, loading } = useUser()

  const projectsData = projects?.projects || {}
  const allProjects = Object.values(projectsData).flat() as Project[]

  const filteredProjects = allProjects.filter(project =>
    project.title?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    project.category?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    project.description?.toLowerCase().includes(searchTerm.toLowerCase())
  )

  if (loading) {
    return <div className={styles["dashboard-container"]}>Loading...</div>
  }

  return (
    <RequireAuth>
      <div className={styles["dashboard-container"]}>

        {/* User Profile Section */}
        {user?.picture && (
          <div className={styles["profile-section"]}>
            <Image
              src={
                user.picture.startsWith('http')
                  ? user.picture
                  : `http://localhost/Google_signup/${user.picture}`
              }
              alt="User profile"
              className={styles["user-avatar"]}
              width={80}
              height={80}
              priority
            />
            <div>
              <p><strong>{user.name}</strong></p>
              <p>{user.email}</p>
            </div>
          </div>
        )}

        {/* Header Section */}
        <div className={styles["header"]}>
          <h1 className={styles["main-title"]}>AI-Powered Personalized Project Topic Selection</h1>
          <p className={styles["intro-text"]}>
            An AI-driven system designed to assist final-year university students in selecting personalized project topics. 
            The system analyzes student profiles, academic interests, and resources to recommend suitable ideas, providing 
            detailed descriptions, step-by-step tutorials, and access to relevant resources.
          </p>
        </div>

        {/* Navigation Menu */}
        <div className={styles["menu"]}>
          <Link href={`/dashboard`} className={styles["menu-link"]}>Browse Projects</Link>
          <span className={styles["menu-separator"]}>|</span>
          <Link href={`../ai`} className={styles["menu-link"]}>Generate Your Own Project</Link>
        </div>

        {/* Search Input */}
        <div className={styles["search"]}>
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className={styles["search-input"]}
            placeholder='Search projects...'
          />
        </div>

        {/* Search Results or Projects */}
        {searchTerm ? (
          <div className={styles["search-results"]}>
            <h3>Search Results</h3>
            <div className={styles["recommendations"]}>
              {filteredProjects.map((project) => (
                <ProjectCard key={project.id} project={project} />
              ))}
            </div>
          </div>
        ) : (
          <div className={styles["content-sections"]}>
            {/* Overview Section */}
            <div className={styles["section"]}>
              <h2 className={styles["section-title"]}>Overview</h2>
              <p className={styles["section-description"]}>
                The AI-Powered Personalized Project Topic Selection system is designed to streamline the project selection 
                process for final-year university students by leveraging machine learning algorithms to analyze student 
                profiles, academic interests, and resources. Each recommendation includes a comprehensive project description, 
                clear objectives, and expected outcomes. The system offers step-by-step tutorials and access to relevant resources.
              </p>
            </div>

            {/* Projects by Category */}
            {Object.entries(projectsData).map(([field, items]) => (
              <div key={field} className={styles["section"]}>
                <h2 className={styles["section-title"]}>
                  {field.charAt(0).toUpperCase() + field.slice(1)} Projects
                </h2>
                <div className={styles["recommendations"]}>
                  {(items as Project[]).map((project) => (
                    <ProjectCard key={project.id} project={project} />
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </RequireAuth>
  )
}

// Project Card Component with proper typing
function ProjectCard({ project }: { project: Project }) {
  return (
    <Link 
      href={`/projectspage/${project.id}`}
      className={styles["project-card"]}
    >
      <div className={styles["card-icon"]}>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
          <path d="M14 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      </div>
      <div className={styles["card-content"]}>
        <div className={styles["card-title"]}>{project.title}</div>
        <div className={styles["card-description"]}>{project.description}</div>
      </div>
      <div className={styles["card-button"]}>View</div>
    </Link>
  )
}