import { createContext, useContext, useState, useEffect } from 'react'
import type { ReactNode } from 'react'
import { auth } from '../lib/api'

type UserType = 'customer' | 'employee' | null

interface AuthContextType {
  user: { id: string; name: string; email: string; role?: string; warehouse_id?: string; warehouse_ids?: string[] } | null
  userType: UserType
  token: string | null
  login: (type: 'customer' | 'employee', email: string, password: string, rememberMe?: boolean) => Promise<void>
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
  logout: () => Promise<void>
  /** Refetch current user from API (e.g. after profile update). */
  refreshUser: () => Promise<void>
  isLoading: boolean
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthContextType['user']>(null)
  const [userType, setUserType] = useState<UserType>(null)
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'))
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const type = localStorage.getItem('userType') as UserType
    if (token && type) {
      setUserType(type)
      if (type === 'customer') {
        auth.customerMe()
          .then((r) => {
            const d = r.data.data
            setUser({ id: d._id, name: d.name, email: d.email })
          })
          .catch(() => {
            localStorage.removeItem('token')
            localStorage.removeItem('userType')
            setToken(null)
            setUserType(null)
          })
          .finally(() => setIsLoading(false))
      } else {
        auth.employeeMe()
          .then((r) => {
            const d = r.data.data as { _id: string; name: string; email: string; role?: string; warehouse_id?: string; warehouse_ids?: string[] }
            setUser({
              id: d._id,
              name: d.name,
              email: d.email,
              role: d.role,
              warehouse_id: d.warehouse_id,
              warehouse_ids: d.warehouse_ids,
            })
          })
          .catch(() => {
            localStorage.removeItem('token')
            localStorage.removeItem('userType')
            setToken(null)
            setUserType(null)
          })
          .finally(() => setIsLoading(false))
      }
    } else {
      setIsLoading(false)
    }
  }, [token])

  const login = async (type: 'customer' | 'employee', email: string, password: string, rememberMe = false) => {
    const res = type === 'customer'
      ? await auth.customerLogin(email, password, rememberMe)
      : await auth.employeeLogin(email, password)
    const data = res.data.data
    const t = type === 'customer' ? (data as { customer: { _id: string; name: string; email: string }; token: string }).token
      : (data as { employee: { _id: string; name: string; email: string }; token: string }).token
    const u = type === 'customer' ? (data as { customer: { _id: string; name: string; email: string } }).customer
      : (data as { employee: { _id: string; name: string; email: string; role?: string; warehouse_id?: string; warehouse_ids?: string[] } }).employee
    localStorage.setItem('token', t)
    localStorage.setItem('userType', type)
    setToken(t)
    setUserType(type)
    setUser(
      type === 'customer'
        ? { id: u._id, name: u.name, email: u.email }
        : { id: u._id, name: u.name, email: u.email, role: u.role, warehouse_id: u.warehouse_id, warehouse_ids: u.warehouse_ids }
    )
  }

  const register = async (name: string, email: string, password: string, passwordConfirmation: string) => {
    const res = await auth.customerRegister({
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    })
    const { customer, token: t } = res.data.data as { customer: { _id: string; name: string; email: string }; token: string }
    localStorage.setItem('token', t)
    localStorage.setItem('userType', 'customer')
    setToken(t)
    setUserType('customer')
    setUser({ id: customer._id, name: customer.name, email: customer.email })
  }

  const logout = async () => {
    if (userType === 'customer') await auth.customerLogout().catch(() => {})
    else if (userType === 'employee') await auth.employeeLogout().catch(() => {})
    localStorage.removeItem('token')
    localStorage.removeItem('userType')
    setToken(null)
    setUserType(null)
    setUser(null)
  }

  const refreshUser = async () => {
    const type = localStorage.getItem('userType') as UserType
    if (!token || !type) return
    if (type === 'customer') {
      const r = await auth.customerMe()
      const d = r.data.data
      setUser({ id: d._id, name: d.name, email: d.email })
    } else {
      const r = await auth.employeeMe()
      const d = r.data.data as { _id: string; name: string; email: string; role?: string; warehouse_id?: string; warehouse_ids?: string[] }
      setUser({
        id: d._id,
        name: d.name,
        email: d.email,
        role: d.role,
        warehouse_id: d.warehouse_id,
        warehouse_ids: d.warehouse_ids,
      })
    }
  }

  return (
    <AuthContext.Provider
      value={{ user, userType, token, login, register, logout, refreshUser, isLoading }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
