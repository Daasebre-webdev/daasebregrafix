'use client';
import Link from 'next/link';
import styles from './home.module.css';
import { useEffect, useState, useCallback, useMemo } from 'react'; // Added useMemo
import { useUser } from '@/app/context/UserContext';

interface Project {
  id: string;
  title: string;
  description: string;
  category: string;
  isUserGenerated?: boolean;
  timestamp?: string;
}

interface QAItem {
  id: string;
  question: string;
  answer: string;
  author: string;
  timestamp: string;
  isNew?: boolean;
}

export default function Home() {
  const { user, loading: authLoading } = useUser();
  const [searchTerm, setSearchTerm] = useState('');
  const [filteredProjects, setFilteredProjects] = useState<Project[]>([]);
  const [userProjects, setUserProjects] = useState<Project[]>([]);
  const [qas, setQas] = useState<QAItem[]>([
    {
      id: '1',
      question: 'This project looks very promising! I\'m excited to see how it can help students.',
      answer: 'Thank you! The AI-powered learning platform is designed to adapt to individual student needs, providing personalized recommendations and feedback to enhance learning outcomes.',
      author: 'Sophia Carter',
      timestamp: '2h'
    },
    {
      id: '2',
      question: 'Are there any specific prerequisites for this project?',
      answer: 'The main prerequisites would be basic programming knowledge and familiarity with machine learning concepts. However, we provide learning resources for beginners to get started.',
      author: 'Ethan Bennett',
      timestamp: '4h'
    }
  ]);
  const [newQuestion, setNewQuestion] = useState('');
  const [isAsking, setIsAsking] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);

  // Memoize predefinedProjects to prevent recreation on every render
  const predefinedProjects = useMemo(() => [
    {
      id: 'pre-1',
      title: "AI-Powered Personalized Learning Platform",
      description: "Develop an AI platform that adapts to individual learning styles.",
      category: "Technology"
    },
    {
      id: 'pre-2',
      title: "Sustainable Energy Solutions for Urban Areas",
      description: "Design sustainable energy solutions for urban environments.",
      category: "Environmental Science"
    },
    {
      id: 'pre-3',
      title: "Innovative Healthcare Technologies",
      description: "Create innovative healthcare technologies to improve patient care.",
      category: "Healthcare"
    }
  ], []);

  // Memoized filter function
  const filterProjects = useCallback(() => {
    const allProjects = [...predefinedProjects, ...userProjects];
    
    if (searchTerm.trim() === '') {
      setFilteredProjects(allProjects);
    } else {
      const filtered = allProjects.filter(project =>
        project.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
        project.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
        project.category.toLowerCase().includes(searchTerm.toLowerCase())
      );
      setFilteredProjects(filtered);
    }
  }, [searchTerm, userProjects, predefinedProjects]);

  useEffect(() => {
    if (typeof window !== 'undefined') {
      const url = new URL(window.location.href);
      const token = url.searchParams.get('token');
      if (token) {
        localStorage.setItem('authToken', token);
        window.location.href = '/dashboard';
      }

      const savedProjects = localStorage.getItem('userProjects');
      if (savedProjects) {
        try {
          const parsedProjects = JSON.parse(savedProjects);
          const recentProjects = parsedProjects
            .filter((project: Project) => {
              const projectDate = new Date(project.timestamp || 0);
              const weekAgo = new Date();
              weekAgo.setDate(weekAgo.getDate() - 7);
              return projectDate > weekAgo;
            })
            .sort((a: Project, b: Project) => 
              new Date(b.timestamp || 0).getTime() - new Date(a.timestamp || 0).getTime()
            );
          setUserProjects(recentProjects);
        } catch (error) {
          console.error('Failed to parse user projects:', error);
        }
      }

      const savedQAs = localStorage.getItem('communityQAs');
      if (savedQAs) {
        try {
          setQas(JSON.parse(savedQAs));
        } catch (error) {
          console.error('Failed to parse Q&A data:', error);
        }
      }
    }
  }, []);

  useEffect(() => {
    filterProjects();
  }, [filterProjects]); // Now using the memoized function

  const handleAskQuestion = () => {
    if (!newQuestion.trim()) return;
    
    setIsGenerating(true);
    
    setTimeout(() => {
      const newQA: QAItem = {
        id: Date.now().toString(),
        question: newQuestion,
        answer: generateAIAnswer(newQuestion),
        author: 'You',
        timestamp: 'just now',
        isNew: true
      };
      
      const updatedQAs = [newQA, ...qas];
      setQas(updatedQAs);
      localStorage.setItem('communityQAs', JSON.stringify(updatedQAs));
      setNewQuestion('');
      setIsAsking(false);
      setIsGenerating(false);
    }, 1500);
  };

  const generateAIAnswer = (question: string): string => {
    const answerMap: Record<string, string> = {
      'prerequisite': 'The prerequisites vary by project but typically include basic programming knowledge and domain-specific fundamentals.',
      'time': 'Most projects can be completed in 3-6 months depending on complexity and time commitment.',
      'resource': 'We recommend starting with our curated learning resources and documentation available in the project dashboard.',
      'team': 'You can find team members through our community forums or work independently if preferred.',
      'difficulty': 'Project difficulty ranges from beginner to advanced, with clear indicators on each project page.',
      'help': 'Our community forum is the best place to get help from both experts and fellow students.'
    };

    const keywords = Object.keys(answerMap);
    const foundKeyword = keywords.find(keyword => 
      question.toLowerCase().includes(keyword)
    );

    return foundKeyword 
      ? answerMap[foundKeyword] 
      : `Our AI analyzed your question about "${question}". For detailed guidance, please check our documentation or community forums. Our team is also happy to help!`;
  };

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
              <p>Get personalized project topic recommendations tailored to your interests and skills.</p>
            </div>
            <div className={styles["explore-btn"]}>
              {authLoading ? (
                <button className={styles["explore-button"]} disabled>
                  Loading...
                </button>
              ) : user ? (
                <Link href="/dashboard" className={styles["explore-button"]}>
                  Go to Dashboard
                </Link>
              ) : (
                <button
                  className={styles["explore-button"]}
                  onClick={() => { window.location.href = googleLoginUrl; }}
                >
                  Sign in with Google
                </button>
              )}
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
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
            </div>
            <div className={styles["featured-projects"]}>
              <h2>Featured Projects</h2>
              <p>Discover trending project ideas and innovative solutions</p>
            </div>
            <div className={styles["left-tabs"]}>
              {filteredProjects.length > 0 ? (
                filteredProjects.map((project) => (
                  <div className={styles["tab"]} key={project.id}>
                    <div className={styles["tab-header"]}>
                      <div className={styles["tab-icon"]}>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M4 19.5C4 18.1193 5.11929 17 6.5 17H20" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          <path d="M6.5 2H20V22H6.5C5.11929 22 4 20.8807 4 19.5V2Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          <path d="M6.5 2C5.11929 2 4 3.11929 4 4.5V19.5C4 20.8807 5.11929 22 6.5 22" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                      </div>
                      {project.isUserGenerated && (
                        <span className={styles["new-badge"]}>New</span>
                      )}
                    </div>
                    <div className={styles["tab-content"]}>
                      <h3>{project.title}</h3>
                      <p>{project.description}</p>
                      <div className={styles["tab-category"]}>
                        <span>{project.category}</span>
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className={styles["no-results"]}>
                  <p>No projects found matching your search.</p>
                  <button 
                    className={styles["explore-button"]}
                    onClick={() => setSearchTerm('')}
                  >
                    Clear search
                  </button>
                </div>
              )}
            </div>
          </div>

          <div className={styles["right-tabs"]}>
            <div className={styles["side-tab"]}>
              <div className={styles["qa-header"]}>
                <h3>Community Q&A</h3>
                {!isAsking ? (
                  <button 
                    className={styles["ask-button"]}
                    onClick={() => setIsAsking(true)}
                  >
                    Ask Question
                  </button>
                ) : (
                  <button 
                    className={styles["cancel-button"]}
                    onClick={() => {
                      setIsAsking(false);
                      setNewQuestion('');
                    }}
                  >
                    Cancel
                  </button>
                )}
              </div>

              {isAsking && (
                <div className={styles["ask-container"]}>
                  <textarea
                    value={newQuestion}
                    onChange={(e) => setNewQuestion(e.target.value)}
                    placeholder="Type your question here..."
                    className={styles["question-input"]}
                    rows={3}
                  />
                  <button
                    onClick={handleAskQuestion}
                    disabled={!newQuestion.trim() || isGenerating}
                    className={styles["submit-question"]}
                  >
                    {isGenerating ? (
                      <>
                        <span className={styles["spinner"]}></span>
                        Generating Answer...
                      </>
                    ) : (
                      'Submit Question'
                    )}
                  </button>
                </div>
              )}

              <div className={styles["qa-list"]}>
                {qas.map((qa) => (
                  <div key={qa.id} className={styles["qa-item"]}>
                    <div className={styles["qa-question"]}>
                      <div className={styles["qa-meta"]}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2"/>
                          <path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                          <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                          <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                        </svg>
                        <span className={styles["qa-author"]}>{qa.author}</span>
                        <span className={styles["qa-time"]}>{qa.timestamp}</span>
                        {qa.isNew && <span className={styles["new-badge"]}>New</span>}
                      </div>
                      <p>{qa.question}</p>
                    </div>
                    <div className={styles["qa-answer"]}>
                      <div className={styles["answer-meta"]}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#4f46e5" strokeWidth="2"/>
                          <path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="#4f46e5" strokeWidth="2" strokeLinecap="round"/>
                          <path d="M9 9H9.01" stroke="#4f46e5" strokeWidth="2" strokeLinecap="round"/>
                          <path d="M15 9H15.01" stroke="#4f46e5" strokeWidth="2" strokeLinecap="round"/>
                        </svg>
                        <span className={styles["ai-label"]}>AI Assistant</span>
                      </div>
                      <p>{qa.answer}</p>
                    </div>
                  </div>
                ))}
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