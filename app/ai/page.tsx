'use client'

import { useState, useEffect } from 'react'
import styles from './ai.module.css'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useUser } from '../context/UserContext'
import Image from 'next/image'

interface FormData {
  field: string
  skills: string
  interests: string
  complexity: string
  technologies: string
}

interface Project {
  id?: string
  title: string
  description: string
  field?: string
  technologies?: string
  isUserGenerated?: boolean
  timestamp?: string
}

export default function AIGenerator() {
  const router = useRouter()
  const { user, loading } = useUser()

  const [formData, setFormData] = useState<FormData>({
    field: '',
    skills: '',
    interests: '',
    complexity: 'medium',
    technologies: ''
  })

  const [, setGeneratedProjects] = useState<Project[]>([]);
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!loading && !user) {
      router.push('http://localhost/Google_signup/index.php')
    }
  }, [user, loading, router])

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({ ...prev, [name]: value }))
  }

  const saveGeneratedProjects = (projects: Project[]) => {
    localStorage.setItem('generatedProjects', JSON.stringify(projects))
    
    const existingUserProjects = JSON.parse(localStorage.getItem('userProjects') || '[]')
    
    const newUserProjects = projects.map(project => ({
      ...project,
      id: `user-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      field: formData.field,
      technologies: formData.technologies,
      isUserGenerated: true,
      timestamp: new Date().toISOString()
    }))
    
    const updatedUserProjects = [...existingUserProjects, ...newUserProjects]
    localStorage.setItem('userProjects', JSON.stringify(updatedUserProjects))
  }

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setIsLoading(true)
    setError(null)

    try {
      const response = await fetch('/api/gemini/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.error || 'Failed to generate projects')
      }

      const data = await response.json()

      let parsedProjects: Project[]
      try {
        parsedProjects = JSON.parse(data.text)
        if (!Array.isArray(parsedProjects)) {
          throw new Error("Parsed data is not an array")
        }
      } catch (err) {
        console.error("Error parsing data.text:", err)
        setError("Failed to parse generated projects")
        return
      }

      setGeneratedProjects(parsedProjects)
      saveGeneratedProjects(parsedProjects)
      router.push('/results')
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An unknown error occurred'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }

  if (loading || !user) {
    return <div className={styles.loading}>Checking authentication...</div>
  }

  return (
    <div className={styles["dashboard-container"]}>
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
            width={40}
            height={40}
          />
          <div>
            <p><strong>{user.name}</strong></p>
            <p>{user.email}</p>
          </div>
        </div>
      )}


      <div className={styles["header"]}>
        <h1 className={styles["main-title"]}>Generate Your Own Project</h1>
        <p className={styles["intro-text"]}>
          Tell us about your interests, skills, and preferences to get personalized project recommendations.
        </p>
      </div>

      <div className={styles["menu"]}>
        <Link href="/dashboard" className={styles["menu-link"]}>Browse Projects</Link>
        <span className={styles["menu-separator"]}>|</span>
        <Link href="/ai" className={styles["menu-link"]}>Generate Your Own Project</Link>
      </div>

      <div className={styles["ai-generator"]}>
        <form onSubmit={handleSubmit} className={styles["ai-form"]}>
          <h2 className={styles["form-title"]}>Project Criteria</h2>

          <div className={styles["form-group"]}>
            <label>Field of Study</label>
            <input
              type="text"
              name="field"
              value={formData.field}
              onChange={handleChange}
              placeholder="e.g., Computer Science, Biology"
              required
            />
          </div>

          <div className={styles["form-group"]}>
            <label>Your Skills</label>
            <input
              type="text"
              name="skills"
              value={formData.skills}
              onChange={handleChange}
              placeholder="e.g., Python, Data Analysis"
              required
            />
          </div>

          <div className={styles["form-group"]}>
            <label>Your Interests</label>
            <input
              type="text"
              name="interests"
              value={formData.interests}
              onChange={handleChange}
              placeholder="e.g., AI, Renewable Energy"
              required
            />
          </div>

          <div className={styles["form-group"]}>
            <label>Project Complexity</label>
            <select
              name="complexity"
              value={formData.complexity}
              onChange={handleChange}
            >
              <option value="easy">Easy</option>
              <option value="medium">Medium</option>
              <option value="hard">Hard</option>
            </select>
          </div>

          <div className={styles["form-group"]}>
            <label>Preferred Technologies</label>
            <input
              type="text"
              name="technologies"
              value={formData.technologies}
              onChange={handleChange}
              placeholder="e.g., React, TensorFlow"
            />
          </div>

          <button type="submit" disabled={isLoading} className={styles["submit-button"]}>
            {isLoading ? 'Generating...' : 'Generate Projects'}
          </button>

          {error && <p className={styles["error"]}>{error}</p>}
        </form>
      </div>
    </div>
  )
}