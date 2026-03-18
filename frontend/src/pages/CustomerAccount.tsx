import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { auth, type Customer, type CustomerProfileUpdateData } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

type FieldErrors = Record<string, string[]>

function SectionTitle({ title }: { title: string }) {
  return (
    <h2
      className="text-base font-bold mb-2"
      style={{ color: 'var(--color-text)' }}
    >
      {title}
    </h2>
  )
}

function Card({ children }: { children: React.ReactNode }) {
  return (
    <div
      className="rounded-xl border p-4 sm:p-5"
      style={{
        borderColor: 'var(--color-border)',
        backgroundColor: 'var(--color-bg)',
      }}
    >
      {children}
    </div>
  )
}

export function CustomerAccount() {
  const { t } = useTranslation()
  const { refreshUser } = useAuth()
  const queryClient = useQueryClient()
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})

  const { data, isLoading } = useQuery({
    queryKey: ['customer-me'],
    queryFn: async () => {
      const res = await auth.customerMe()
      return res.data.data as Customer
    },
  })

  const updateMutation = useMutation({
    mutationFn: (payload: CustomerProfileUpdateData) => auth.updateProfile(payload),
    onSuccess: () => {
      setFieldErrors({})
      queryClient.invalidateQueries({ queryKey: ['customer-me'] })
      refreshUser()
    },
  })

  const customer = data

  if (isLoading || !customer) {
    return (
      <div className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
        {t('common.loading')}
      </div>
    )
  }

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const form = e.currentTarget
    const payload: CustomerProfileUpdateData = {
      name: (form.querySelector('[name="name"]') as HTMLInputElement)?.value?.trim() || undefined,
      email: (form.querySelector('[name="email"]') as HTMLInputElement)?.value?.trim() || undefined,
      phone: (form.querySelector('[name="phone"]') as HTMLInputElement)?.value?.trim() || undefined,
      address: (form.querySelector('[name="address"]') as HTMLInputElement)?.value?.trim() || undefined,
      city: (form.querySelector('[name="city"]') as HTMLInputElement)?.value?.trim() || undefined,
      country: (form.querySelector('[name="country"]') as HTMLInputElement)?.value?.trim() || undefined,
      postal_code: (form.querySelector('[name="postal_code"]') as HTMLInputElement)?.value?.trim() || undefined,
    }
    const password = (form.querySelector('[name="password"]') as HTMLInputElement)?.value?.trim()
    if (password) {
      payload.password = password
      payload.password_confirmation = (form.querySelector('[name="password_confirmation"]') as HTMLInputElement)?.value?.trim() || undefined
    }
    setFieldErrors({})
    updateMutation.mutate(payload, {
      onError: (err: { response?: { data?: { message?: string; data?: { errors?: FieldErrors } } } }) => {
        const errors = err?.response?.data?.data?.errors ?? err?.response?.data?.errors
        if (errors && typeof errors === 'object') {
          setFieldErrors(errors as FieldErrors)
          const firstMessages = Object.values(errors).flat()
          if (firstMessages.length) alert(firstMessages[0])
          return
        }
        alert(err?.response?.data?.message ?? t('common.error'))
      },
    })
  }

  const getError = (field: string) => {
    const list = fieldErrors[field] ?? fieldErrors[field.replace(/_/g, '.')]
    return Array.isArray(list) ? list[0] : undefined
  }

  const inputClass = 'w-full px-4 py-2 border rounded-lg'
  const inputStyle = (field: string) => ({
    borderColor: getError(field) ? '#dc2626' : 'var(--color-border)',
  })

  return (
    <div>
      <h1 className="text-2xl font-bold mb-6" style={{ color: 'var(--color-text)' }}>
        {t('account.title')}
      </h1>

      <form onSubmit={handleSubmit} className="max-w-md space-y-6">
        {/* Personal Information — same section and order as app */}
        <div>
          <SectionTitle title={t('account.personalInformation')} />
          <Card>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.name')}
                </label>
                <input
                  type="text"
                  name="name"
                  defaultValue={customer.name}
                  className={inputClass}
                  style={inputStyle('name')}
                />
                {getError('name') && <p className="mt-1 text-sm text-red-600">{getError('name')}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.phone')}
                </label>
                <input
                  type="text"
                  name="phone"
                  defaultValue={customer.phone ?? ''}
                  placeholder={t('account.notSet')}
                  className={inputClass}
                  style={inputStyle('phone')}
                />
                {getError('phone') && <p className="mt-1 text-sm text-red-600">{getError('phone')}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.email')}
                </label>
                <input
                  type="email"
                  name="email"
                  defaultValue={customer.email}
                  className={inputClass}
                  style={inputStyle('email')}
                />
                {getError('email') && <p className="mt-1 text-sm text-red-600">{getError('email')}</p>}
              </div>
            </div>
          </Card>
        </div>

        {/* Shipping Address — same section and order as app */}
        <div>
          <SectionTitle title={t('account.shippingAddressSection')} />
          <Card>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.address')}
                </label>
                <input
                  type="text"
                  name="address"
                  defaultValue={customer.address ?? ''}
                  placeholder={t('account.notSet')}
                  className={inputClass}
                  style={inputStyle('address')}
                />
                {getError('address') && <p className="mt-1 text-sm text-red-600">{getError('address')}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.city')}
                </label>
                <input
                  type="text"
                  name="city"
                  defaultValue={customer.city ?? ''}
                  placeholder={t('account.notSet')}
                  className={inputClass}
                  style={inputStyle('city')}
                />
                {getError('city') && <p className="mt-1 text-sm text-red-600">{getError('city')}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.postalCode')}
                </label>
                <input
                  type="text"
                  name="postal_code"
                  defaultValue={customer.postal_code ?? ''}
                  placeholder={t('account.notSet')}
                  className={inputClass}
                  style={inputStyle('postal_code')}
                />
                {getError('postal_code') && <p className="mt-1 text-sm text-red-600">{getError('postal_code')}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.country')}
                </label>
                <input
                  type="text"
                  name="country"
                  defaultValue={customer.country ?? ''}
                  placeholder={t('account.notSet')}
                  className={inputClass}
                  style={inputStyle('country')}
                />
                {getError('country') && <p className="mt-1 text-sm text-red-600">{getError('country')}</p>}
              </div>
            </div>
          </Card>
        </div>

        {/* Password — optional, same as app allows no password change */}
        <div>
          <SectionTitle title={t('account.passwordSection')} />
          <Card>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.password')} <span className="text-stone-400">({t('account.optional')})</span>
                </label>
                <input
                  type="password"
                  name="password"
                  placeholder="••••••••"
                  className={inputClass}
                  style={inputStyle('password')}
                />
                <p className="mt-1 text-xs text-stone-500">{t('account.passwordHint')}</p>
                {getError('password') && <p className="mt-1 text-sm text-red-600">{getError('password')}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-muted)' }}>
                  {t('account.passwordConfirmation')}
                </label>
                <input
                  type="password"
                  name="password_confirmation"
                  placeholder="••••••••"
                  className={inputClass}
                  style={inputStyle('password_confirmation')}
                />
                {getError('password_confirmation') && <p className="mt-1 text-sm text-red-600">{getError('password_confirmation')}</p>}
              </div>
            </div>
          </Card>
        </div>

        {updateMutation.isSuccess && (
          <p className="text-sm text-green-600">{t('account.saved')}</p>
        )}
        <button
          type="submit"
          disabled={updateMutation.isPending}
          className="px-4 py-2 rounded-lg font-medium text-white disabled:opacity-50"
          style={{ background: 'var(--color-primary)' }}
        >
          {updateMutation.isPending ? t('common.saving') : t('account.save')}
        </button>
      </form>
    </div>
  )
}
