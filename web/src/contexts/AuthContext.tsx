import { useState, useEffect } from 'react'
import type { ReactNode } from 'react'
import { auth } from '../lib/api'
import { AuthContext, type AuthContextType, type UserType } from './authContextCore'

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
            const d = r.data.data as { _id: string; name: string; email: string; role?: string; warehouse_id?: string; warehouse_ids?: string[]; publisher_id?: string }
            setUser({
              id: d._id,
              name: d.name,
              email: d.email,
              role: d.role,
              warehouse_id: d.warehouse_id,
              warehouse_ids: d.warehouse_ids,
              publisher_id: d.publisher_id,
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
    localStorage.setItem('token', t)
    localStorage.setItem('userType', type)
    setToken(t)
    setUserType(type)
    if (type === 'customer') {
      const customer = (data as { customer: { _id: string; name: string; email: string } }).customer
      setUser({ id: customer._id, name: customer.name, email: customer.email })
      return { role: undefined }
    }

    const employee = (data as {
      employee: {
        _id: string
        name: string
        email: string
        role?: string
        warehouse_id?: string
        warehouse_ids?: string[]
        publisher_id?: string
      }
    }).employee
    setUser({
      id: employee._id,
      name: employee.name,
      email: employee.email,
      role: employee.role,
      warehouse_id: employee.warehouse_id,
      warehouse_ids: employee.warehouse_ids,
      publisher_id: employee.publisher_id,
    })
    return { role: employee.role }
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
      const d = r.data.data as { _id: string; name: string; email: string; role?: string; warehouse_id?: string; warehouse_ids?: string[]; publisher_id?: string }
      setUser({
        id: d._id,
        name: d.name,
        email: d.email,
        role: d.role,
        warehouse_id: d.warehouse_id,
        warehouse_ids: d.warehouse_ids,
        publisher_id: d.publisher_id,
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
