import { useState, useMemo, useEffect } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { orders, settings, auth } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

type PaymentMethodOption = { id: string; name: string; enabled: boolean }

function parsePaymentMethods(raw: unknown): PaymentMethodOption[] {
  if (!Array.isArray(raw)) return []
  return raw
    .filter((item): item is Record<string, unknown> => item && typeof item === 'object' && typeof (item as { id?: unknown }).id === 'string')
    .map((item) => ({
      id: String(item.id),
      name: String(item.name ?? item.id),
      enabled: Boolean(item.enabled),
    }))
}

export function Checkout() {
  const [address, setAddress] = useState('')
  const [city, setCity] = useState('')
  const [country, setCountry] = useState('')
  const [postalCode, setPostalCode] = useState('')
  const [paymentMethod, setPaymentMethod] = useState<string>('')
  const { userType } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  const { data: profileData } = useQuery({
    queryKey: ['customer-me'],
    queryFn: async () => {
      const res = await auth.customerMe()
      return res.data
    },
    enabled: userType === 'customer',
  })

  const customer = (profileData?.data as { address?: string; city?: string; country?: string; postal_code?: string } | undefined) | undefined
  useEffect(() => {
    if (!customer) return
    setAddress((prev) => (prev === '' && customer.address != null ? customer.address : prev))
    setCity((prev) => (prev === '' && customer.city != null ? customer.city : prev))
    setCountry((prev) => (prev === '' && customer.country != null ? customer.country : prev))
    setPostalCode((prev) => (prev === '' && customer.postal_code != null ? customer.postal_code : prev))
  }, [customer])

  const { data: settingsData } = useQuery({
    queryKey: ['settings'],
    queryFn: async () => {
      const res = await settings.get()
      return res.data
    },
  })

  const rawPaymentMethods = (settingsData?.data as { payment_methods?: unknown })?.payment_methods
  const allMethods = useMemo(() => parsePaymentMethods(rawPaymentMethods), [rawPaymentMethods])
  const enabledMethods = useMemo(() => allMethods.filter((m) => m.enabled), [allMethods])
  const defaultMethod = enabledMethods[0]?.id ?? ''
  const selectedMethod = enabledMethods.some((m) => m.id === paymentMethod) ? paymentMethod : defaultMethod

  const checkout = useMutation({
    mutationFn: () =>
      orders.checkout(
        { address, city, country, postal_code: postalCode || undefined },
        selectedMethod
      ),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cart'] })
      navigate('/orders')
    },
  })

  if (userType !== 'customer') {
    navigate('/login')
    return null
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (enabledMethods.length === 0) {
      return
    }
    checkout.mutate()
  }

  return (
    <div className="max-w-md mx-auto">
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">{t('checkout.title')}</h1>
      <p className="text-stone-600 mb-4">{t('checkout.shippingAddress')}</p>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">{t('checkout.address')}</label>
          <input
            type="text"
            value={address}
            onChange={(e) => setAddress(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">{t('checkout.city')}</label>
          <input
            type="text"
            value={city}
            onChange={(e) => setCity(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">{t('checkout.country')}</label>
          <input
            type="text"
            value={country}
            onChange={(e) => setCountry(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">{t('checkout.postalCode')}</label>
          <input
            type="text"
            value={postalCode}
            onChange={(e) => setPostalCode(e.target.value)}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-stone-700 mb-2">{t('checkout.paymentMethod')}</label>
          {enabledMethods.length === 0 ? (
            <p className="text-amber-700 text-sm">{t('checkout.noPaymentMethods')}</p>
          ) : (
            <div className="space-y-2">
              {enabledMethods.map((m) => (
                <label key={m.id} className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    name="payment_method"
                    value={m.id}
                    checked={selectedMethod === m.id}
                    onChange={() => setPaymentMethod(m.id)}
                    className="rounded-full border-stone-300 text-amber-900 focus:ring-amber-500"
                  />
                  <span>{m.name}</span>
                </label>
              ))}
            </div>
          )}
        </div>

        {checkout.error && (
          <p className="text-red-600 text-sm">
            {(checkout.error as { response?: { data?: { message?: string } } })?.response?.data?.message || t('common.error')}
          </p>
        )}
        <button
          type="submit"
          disabled={checkout.isPending || enabledMethods.length === 0}
          className="w-full py-2.5 rounded-lg font-medium text-white hover:opacity-90 disabled:opacity-50 transition-opacity"
          style={{ background: 'var(--color-primary)' }}
        >
          {t('checkout.placeOrder')}
        </button>
      </form>
    </div>
  )
}
