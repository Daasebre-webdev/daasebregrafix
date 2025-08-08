'use client'

import { createContext, useContext, useEffect, useState, ReactNode } from 'react'

interface User {
  name: string
  email: string
  picture: string
}

interface UserContextType {
  user: User | null
  loading: boolean
  logout: () => void
  fetchUser: () => Promise<void>
}

const UserContext = createContext<UserContextType | undefined>(undefined)

export function UserProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  const fetchUser = async () => {
    try {
      const response = await fetch('http://localhost/Google_signup/get_user.php', {
        credentials: 'include',
      })

      if (!response.ok) throw new Error('Not authenticated')

      const userData = await response.json()

      if (userData.picture && !userData.picture.startsWith('http')) {
        userData.picture = `http://localhost/Google_signup/${userData.picture}`
      }

      setUser(userData)
    } catch {
      setUser(null)
    } finally {
      setLoading(false)
    }
  }

  const logout = async () => {
    try {
      await fetch('http://localhost/Google_signup/logout.php', {
        method: 'POST',
        credentials: 'include',
      })
    } finally {
      setUser(null)
      window.location.href = '/'
    }
  }

  useEffect(() => {
    fetchUser()
  }, [])

  return (
    <UserContext.Provider value={{ user, loading, logout, fetchUser }}>
      {children}
    </UserContext.Provider>
  )
}

export function useUser() {
  const context = useContext(UserContext)
  if (context === undefined) {
    throw new Error('useUser must be used within a UserProvider')
  }
  return context
}
