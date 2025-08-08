// Get auth token from localStorage
export function getAuthToken(): string | null {
  if (typeof window !== 'undefined') {
    return localStorage.getItem('authToken');
  }
  return null;
}
// helpers.ts
import projects from '../data/projects.json';

export interface Project {
  id: string;
  title: string;
  category: string;
  description: string;
  details?: {
    fullDescription?: string;
    difficulty?: string;
    duration?: string;
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

export function getAllProjects(): Project[] {
  try {
    const staticProjects = Object.values(projects.projects).flat() as Project[];

    if (typeof window !== 'undefined') {
      const generatedProjects = JSON.parse(
        localStorage.getItem('generatedProjects') || '[]'
      ) as Project[];
      return [...staticProjects, ...generatedProjects];
    }

    return staticProjects;
  } catch (error) {
    console.error('Error loading projects:', error);
    return [];
  }
}
