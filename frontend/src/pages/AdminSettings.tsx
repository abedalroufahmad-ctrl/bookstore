import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { admin } from '../lib/api'
import { useTranslation } from 'react-i18next'

export type WeightUnit = 'kg' | 'g' | 'lb' | 'oz'

export type PaymentMethodItem = { id: string; name: string; enabled: boolean }

const DEFAULT_PAYMENT_METHODS: PaymentMethodItem[] = [
  { id: 'cod', name: 'Cash on Delivery (COD)', enabled: true },
  { id: 'stripe', name: 'Credit/Debit Card (Stripe)', enabled: false },
  { id: 'paypal', name: 'PayPal', enabled: false },
]

function normalizePaymentMethods(raw: unknown): PaymentMethodItem[] {
  if (Array.isArray(raw) && raw.length > 0) {
    return raw.map((item: Record<string, unknown>) => ({
      id: String(item.id ?? ''),
      name: String(item.name ?? item.id ?? ''),
      enabled: Boolean(item.enabled),
    }))
  }
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    const obj = raw as Record<string, boolean>
    return Object.entries(obj).map(([id, enabled]) => ({
      id,
      name: id,
      enabled: Boolean(enabled),
    }))
  }
  return DEFAULT_PAYMENT_METHODS
}

export function AdminSettings() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [globalDiscount, setGlobalDiscount] = useState<number>(0)
  const [weightUnit, setWeightUnit] = useState<WeightUnit>('kg')
  const [catalogItemsPerPage, setCatalogItemsPerPage] = useState<number>(35)
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethodItem[]>(DEFAULT_PAYMENT_METHODS)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [editingIndex, setEditingIndex] = useState<number | null>(null)
  const [newMethodId, setNewMethodId] = useState('')
  const [newMethodName, setNewMethodName] = useState('')
  const [showAddForm, setShowAddForm] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['admin-settings'],
    queryFn: async () => {
      const res = await admin.settings.get()
      return res.data
    },
  })

  useEffect(() => {
    if (data?.data) {
      const d = data.data as Record<string, unknown>
      setGlobalDiscount(Number(d.global_discount) ?? 0)
      setWeightUnit((d.weight_unit as WeightUnit) || 'kg')
      const perPage = Number(d.catalog_items_per_page)
      setCatalogItemsPerPage(perPage >= 1 && perPage <= 100 ? Math.round(perPage) : 35)
      setPaymentMethods(normalizePaymentMethods(d.payment_methods))
    }
  }, [data])

  const updateMutation = useMutation({
    mutationFn: (payload: {
      global_discount?: number
      weight_unit?: WeightUnit
      catalog_items_per_page?: number
      payment_methods?: PaymentMethodItem[]
    }) => admin.settings.update(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-settings'] })
      queryClient.invalidateQueries({ queryKey: ['settings'] })
      setError('')
      setMessage(t('common.saved') || 'Saved successfully')
      setTimeout(() => setMessage(''), 3000)
    },
    onError: (err: { response?: { data?: { message?: string; data?: { errors?: unknown } } } }) => {
      const msg = err?.response?.data?.message ?? t('common.error')
      const details = err?.response?.data?.data?.errors
      setError(typeof details === 'object' ? `${msg}: ${JSON.stringify(details)}` : msg)
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    updateMutation.mutate({
      global_discount: globalDiscount,
      weight_unit: weightUnit,
      catalog_items_per_page: catalogItemsPerPage,
      payment_methods: paymentMethods,
    })
  }

  const updateMethod = (index: number, updates: Partial<PaymentMethodItem>) => {
    setPaymentMethods((prev) =>
      prev.map((m, i) => (i === index ? { ...m, ...updates } : m))
    )
    setEditingIndex(null)
  }

  const removeMethod = (index: number) => {
    if (window.confirm(t('admin.settings.confirmDeletePaymentMethod') || 'Remove this payment method?')) {
      setPaymentMethods((prev) => prev.filter((_, i) => i !== index))
      setEditingIndex(null)
    }
  }

  const addMethod = () => {
    const id = newMethodId.trim().toLowerCase().replace(/\s+/g, '_')
    const name = newMethodName.trim() || id
    if (!id) return
    if (paymentMethods.some((m) => m.id === id)) {
      setError(t('admin.settings.paymentMethodIdExists') || 'This ID already exists.')
      return
    }
    setPaymentMethods((prev) => [...prev, { id, name, enabled: true }])
    setNewMethodId('')
    setNewMethodName('')
    setShowAddForm(false)
    setError('')
  }

  if (isLoading) return <div className="p-8">{t('common.loading')}</div>

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-bold text-amber-900 mb-6">{t('admin.settings.title') || 'Admin Settings'}</h1>

      {error && (
        <div className="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
          {error}
        </div>
      )}
      {message && (
        <div className="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
          {message}
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-white p-6 rounded-xl border border-stone-200 shadow-sm space-y-6">
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-2">
            {t('admin.settings.globalDiscount') || 'Global Discount (%)'}
          </label>
          <div className="flex items-center gap-4">
            <input
              type="number"
              min="0"
              max="100"
              value={globalDiscount}
              onChange={(e) => setGlobalDiscount(parseFloat(e.target.value) || 0)}
              className="w-32 px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
            <span className="text-stone-500">%</span>
          </div>
          <p className="mt-2 text-xs text-stone-500">
            {t('admin.settings.globalDiscountHint') || 'This discount applies to all books that do not have a special discount set.'}
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-stone-700 mb-2">
            {t('admin.settings.catalogItemsPerPage') || 'Catalog items per page'}
          </label>
          <div className="flex items-center gap-4">
            <input
              type="number"
              min={1}
              max={100}
              value={catalogItemsPerPage}
              onChange={(e) => setCatalogItemsPerPage(Math.min(100, Math.max(1, parseInt(e.target.value, 10) || 35)))}
              className="w-32 px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
          </div>
          <p className="mt-2 text-xs text-stone-500">
            {t('admin.settings.catalogItemsPerPageHint') || 'Number of books, categories, or authors per page on the public books list, category list, author list, and home page.'}
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-stone-700 mb-2">
            {t('admin.settings.weightUnit') || 'Weight unit'}
          </label>
          <select
            value={weightUnit}
            onChange={(e) => setWeightUnit(e.target.value as WeightUnit)}
            className="px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          >
            <option value="kg">{t('admin.settings.weightKg') || 'Kilograms (kg)'}</option>
            <option value="g">{t('admin.settings.weightG') || 'Grams (g)'}</option>
            <option value="lb">{t('admin.settings.weightLb') || 'Pounds (lb)'}</option>
            <option value="oz">{t('admin.settings.weightOz') || 'Ounces (oz)'}</option>
          </select>
          <p className="mt-2 text-xs text-stone-500">
            {t('admin.settings.weightUnitHint') || 'Book weights are stored in kg and displayed in this unit across the site.'}
          </p>
        </div>

        <div className="border border-stone-200 rounded-lg p-4 bg-stone-50/50">
          <h2 className="text-lg font-semibold text-amber-900 mb-1">
            {t('admin.settings.managePaymentMethods') || 'Manage payment methods'}
          </h2>
          <p className="text-sm text-stone-500 mb-4">
            {t('admin.settings.paymentMethodsHint') || 'Add, edit or remove payment options. Customers see only enabled methods at checkout.'}
          </p>

          <ul className="space-y-2 mb-4">
            {paymentMethods.map((method, index) => (
              <li
                key={method.id}
                className="flex items-center gap-3 p-3 rounded-lg bg-white border border-stone-200"
              >
                {editingIndex === index ? (
                  <>
                    <input
                      type="text"
                      value={method.name}
                      onChange={(e) => updateMethod(index, { name: e.target.value })}
                      className="flex-1 px-3 py-1.5 border border-stone-300 rounded text-sm"
                      placeholder={t('admin.settings.name') || 'Display name'}
                    />
                    <label className="flex items-center gap-1.5 text-sm whitespace-nowrap">
                      <input
                        type="checkbox"
                        checked={method.enabled}
                        onChange={(e) => updateMethod(index, { enabled: e.target.checked })}
                        className="rounded border-stone-300 text-amber-900"
                      />
                      {t('admin.settings.enabled') || 'Enabled'}
                    </label>
                    <button
                      type="button"
                      onClick={() => setEditingIndex(null)}
                      className="text-sm text-stone-600 hover:text-stone-800"
                    >
                      {t('admin.close') || 'Done'}
                    </button>
                  </>
                ) : (
                  <>
                    <div className="flex-1 min-w-0">
                      <span className="font-medium text-stone-800">{method.name}</span>
                      <span className="ml-2 text-xs text-stone-500 font-mono">({method.id})</span>
                    </div>
                    <span className={`text-xs px-2 py-0.5 rounded ${method.enabled ? 'bg-green-100 text-green-800' : 'bg-stone-100 text-stone-600'}`}>
                      {method.enabled ? t('admin.settings.enabled') || 'Enabled' : t('admin.settings.disabled') || 'Disabled'}
                    </span>
                    <button
                      type="button"
                      onClick={() => setEditingIndex(index)}
                      className="text-amber-700 hover:underline text-sm"
                    >
                      {t('admin.edit') || 'Edit'}
                    </button>
                    <button
                      type="button"
                      onClick={() => removeMethod(index)}
                      className="text-red-600 hover:underline text-sm"
                    >
                      {t('admin.delete') || 'Delete'}
                    </button>
                  </>
                )}
              </li>
            ))}
          </ul>

          {showAddForm ? (
            <div className="p-3 rounded-lg bg-white border border-amber-200 space-y-2">
              <input
                type="text"
                value={newMethodId}
                onChange={(e) => setNewMethodId(e.target.value)}
                placeholder={t('admin.settings.paymentMethodIdPlaceholder') || 'Method ID (e.g. bank_transfer)'}
                className="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm"
              />
              <input
                type="text"
                value={newMethodName}
                onChange={(e) => setNewMethodName(e.target.value)}
                placeholder={t('admin.settings.paymentMethodNamePlaceholder') || 'Display name (e.g. Bank Transfer)'}
                className="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm"
              />
              <div className="flex gap-2">
                <button type="button" onClick={addMethod} className="px-3 py-1.5 bg-amber-900 text-amber-50 rounded-lg text-sm">
                  {t('admin.add') || 'Add'}
                </button>
                <button type="button" onClick={() => { setShowAddForm(false); setNewMethodId(''); setNewMethodName(''); setError(''); }} className="px-3 py-1.5 border border-stone-300 rounded-lg text-sm">
                  {t('admin.cancel') || 'Cancel'}
                </button>
              </div>
            </div>
          ) : (
            <button
              type="button"
              onClick={() => setShowAddForm(true)}
              className="text-sm text-amber-700 hover:underline"
            >
              + {t('admin.settings.addPaymentMethod') || 'Add payment method'}
            </button>
          )}

          {paymentMethods.length > 0 && !paymentMethods.some((m) => m.enabled) && (
            <p className="mt-3 text-amber-700 text-sm">
              {t('admin.settings.atLeastOnePaymentMethod') || 'Enable at least one payment method so customers can checkout.'}
            </p>
          )}
        </div>

        <div className="flex items-center gap-4">
          <button
            type="submit"
            disabled={updateMutation.isPending}
            className="px-6 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
          >
            {updateMutation.isPending ? t('common.saving') : t('common.save')}
          </button>
        </div>
      </form>
    </div>
  )
}
