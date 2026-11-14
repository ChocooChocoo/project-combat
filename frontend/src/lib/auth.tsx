import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { api, token } from '@/lib/api'
import type { GymSubscriptionSummary } from '@/lib/subscriptions'

export type GymAccess = {
  id: number
  name: string
  status: string
  roles: string[]
  role_names?: string[]
  permissions: string[]
  subscription?: GymSubscriptionSummary
}

export type User = {
  id: number
  first_name: string
  middle_name: string | null
  last_name: string
  suffix: string | null
  full_name: string
  birthdate: string | null
  city: string | null
  province: string | null
  email: string
  account_type: 'member' | 'owner'
  has_member_records: boolean
  phone: string | null
  photo_url: string | null
  email_verified: boolean
  is_platform_admin: boolean
  password_change_required: boolean
  gyms: GymAccess[]
}

type AuthState = {
  user: User | null
  loading: boolean
  signIn: (pass: string, user: User) => void
  setUser: (user: User) => void
  refresh: () => Promise<void>
  signOut: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    if (!token.get()) {
      setUser(null)
      return
    }
    try {
      setUser((await api<{ data: User }>('/me')).data)
    } catch {
      // Expired or cancelled pass: start again from sign-in.
      token.clear()
      setUser(null)
    }
  }, [])

  useEffect(() => {
    refresh().finally(() => setLoading(false))
  }, [refresh])

  const signIn = (pass: string, signedIn: User) => {
    token.set(pass)
    setUser(signedIn)
  }

  const signOut = async () => {
    await api('/auth/logout', { method: 'POST' }).catch(() => undefined)
    token.clear()
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, loading, signIn, setUser, refresh, signOut }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthState {
  const value = useContext(AuthContext)
  if (!value) throw new Error('useAuth must be used inside AuthProvider')
  return value
}
