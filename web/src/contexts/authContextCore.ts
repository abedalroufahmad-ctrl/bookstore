import { createContext, useContext } from 'react'

export type UserType = 'customer' | 'employee' | null

export interface AuthContextType {
  user: {
    id: string
    name: string
    email: string
    role?: string
    warehouse_id?: string
    warehouse_ids?: string[]
    publisher_id?: string
  } | null
  userType: UserType
  token: string | null
  login: (
    type: 'customer' | 'employee',
    email: string,
    password: string,
    rememberMe?: boolean,
  ) => Promise<{ role?: string }>
  register: (
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string,
  ) => Promise<void>
  logout: () => Promise<void>
  refreshUser: () => Promise<void>
  isLoading: boolean
}

export const AuthContext = createContext<AuthContextType | null>(null)

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}
