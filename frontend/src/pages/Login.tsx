import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../contexts/AuthContext'

export function Login() {
  const [type, setType] = useState<'customer' | 'employee'>('customer')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [rememberMe, setRememberMe] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()
  const { t } = useTranslation()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(type, email, password, type === 'customer' && rememberMe)
      navigate(type === 'employee' ? '/admin' : '/')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || t('auth.loginFailed')
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-md mx-auto">
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">{t('auth.loginTitle')}</h1>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">{t('auth.loginAs')}</label>
          <select
            value={type}
            onChange={(e) => setType(e.target.value as 'customer' | 'employee')}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)]"
          >
            <option value="customer">{t('auth.customer')}</option>
            <option value="employee">{t('auth.employee')}</option>
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">{t('auth.email')}</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)]"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">{t('auth.password')}</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)]"
          />
        </div>
        {type === 'customer' && (
          <label className="flex items-center gap-2 text-sm text-stone-700">
            <input
              type="checkbox"
              checked={rememberMe}
              onChange={(e) => setRememberMe(e.target.checked)}
              className="h-4 w-4 rounded border-stone-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]"
            />
            {t('auth.rememberMe')}
          </label>
        )}
        {error && <p className="text-red-600 text-sm">{error}</p>}
        <button
          type="submit"
          disabled={loading}
          className="w-full py-2.5 rounded-lg font-medium text-white hover:opacity-90 disabled:opacity-50 transition-opacity"
          style={{ background: 'var(--color-primary)' }}
        >
          {loading ? t('auth.loggingIn') : t('auth.loginBtn')}
        </button>
      </form>
      {type === 'customer' && (
        <p className="mt-4 text-center text-sm text-stone-600">
          {t('auth.noAccount')} <Link to="/register" className="text-[var(--color-primary)] font-medium">{t('auth.registerLink')}</Link>
        </p>
      )}
    </div>
  )
}
