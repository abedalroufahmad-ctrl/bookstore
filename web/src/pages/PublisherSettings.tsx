import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useParams, Link } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { api, admin, type PublisherCommissionEntry } from '../lib/api'

interface PublisherSettingsData {
  support_email?: string
  support_phone?: string
  return_policy?: string
  default_discount?: number
  payment_methods?: string[]
  paypal_email?: string
  paypal_merchant_id?: string
  bank_name?: string
  bank_account_number?: string
  platform_commission_percent?: number
}

interface PaymentMethodItem {
  id: string
  name: string
  enabled: boolean
}

export function PublisherSettings() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const queryClient = useQueryClient()
  const { user, userType } = useAuth()
  
  const isPublisherManager = userType === 'employee' && user?.role === 'publisher_manager'
  const isManager = userType === 'employee' && user?.role === 'manager'
  const managedPublisherId = isPublisherManager ? user?.publisher_id : undefined

  // If publisher manager, force their own ID. Otherwise (admin), use URL param.
  const targetId = isPublisherManager ? managedPublisherId : id

  const [form, setForm] = useState<PublisherSettingsData>({
    support_email: '',
    support_phone: '',
    return_policy: '',
    default_discount: 0,
    payment_methods: [],
    paypal_email: '',
    paypal_merchant_id: '',
    bank_name: '',
    bank_account_number: '',
    platform_commission_percent: 0,
  })
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [settleAmount, setSettleAmount] = useState('')
  const [settleReference, setSettleReference] = useState('')
  const [settleNote, setSettleNote] = useState('')
  const [settleError, setSettleError] = useState('')
  const [settleMessage, setSettleMessage] = useState('')

  const { data: globalSettings, isLoading: isLoadingGlobal } = useQuery({
    queryKey: ['admin-settings'],
    queryFn: async () => {
      const res = await admin.settings.get()
      return res.data
    },
  })

  const globalPaymentMethods: PaymentMethodItem[] = (() => {
    if (!globalSettings?.data) return []
    const raw = (globalSettings.data as Record<string, unknown>).payment_methods
    if (Array.isArray(raw) && raw.length > 0) {
      return raw.map((item: Record<string, unknown>) => ({
        id: String(item.id ?? ''),
        name: String(item.name ?? item.id ?? ''),
        enabled: Boolean(item.enabled),
      }))
    }
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
      return Object.entries(raw as Record<string, boolean>).map(([id, enabled]) => ({
        id,
        name: id,
        enabled: Boolean(enabled),
      }))
    }
    return []
  })()

  const { data, isLoading } = useQuery({
    queryKey: ['publisher-settings', targetId],
    queryFn: async () => {
      const res = await api.get(`/admin/publishers/${targetId}/settings`)
      return res.data
    },
    enabled: !!targetId,
  })

  const { data: commissionData, isLoading: isLoadingCommission } = useQuery({
    queryKey: ['publisher-commission-account', targetId],
    queryFn: async () => {
      const res = await admin.publishers.commissionAccount(String(targetId))
      return res.data.data
    },
    enabled: !!targetId,
  })

  useEffect(() => {
    if (data?.data) {
      setForm({
        support_email: data.data.support_email ?? '',
        support_phone: data.data.support_phone ?? '',
        return_policy: data.data.return_policy ?? '',
        default_discount: Number(data.data.default_discount) || 0,
        payment_methods: Array.isArray(data.data.payment_methods) ? data.data.payment_methods : [],
        paypal_email: data.data.paypal_email ?? '',
        paypal_merchant_id: data.data.paypal_merchant_id ?? '',
        bank_name: data.data.bank_name ?? '',
        bank_account_number: data.data.bank_account_number ?? '',
        platform_commission_percent: Number(data.data.platform_commission_percent) || 0,
      })
    }
  }, [data])

  const updateMutation = useMutation({
    mutationFn: (payload: PublisherSettingsData) =>
      api.put(`/admin/publishers/${targetId}/settings`, payload),
    onSuccess: () => {
      setMessage(t('admin.settingsSaved', 'Settings saved successfully!'))
      setError('')
      queryClient.invalidateQueries({ queryKey: ['publisher-settings', targetId] })
      setTimeout(() => setMessage(''), 3000)
    },
    onError: (err: unknown) => {
      const error = err as { response?: { data?: { message?: string } } }
      setError(error?.response?.data?.message || t('common.error'))
      setMessage('')
    },
  })

  const settleMutation = useMutation({
    mutationFn: (payload: { amount: number; note?: string; reference?: string }) =>
      admin.publishers.recordCommissionSettlement(String(targetId), payload),
    onSuccess: () => {
      setSettleMessage(t('admin.commissionTransferRecorded', 'Transfer recorded. Thank you.'))
      setSettleError('')
      setSettleAmount('')
      setSettleReference('')
      setSettleNote('')
      queryClient.invalidateQueries({ queryKey: ['publisher-commission-account', targetId] })
      setTimeout(() => setSettleMessage(''), 3000)
    },
    onError: (err: unknown) => {
      const error = err as { response?: { data?: { message?: string } } }
      setSettleError(error?.response?.data?.message || t('common.error'))
      setSettleMessage('')
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    updateMutation.mutate({
      ...form,
      default_discount: Number(form.default_discount),
      platform_commission_percent: Number(form.platform_commission_percent),
    })
  }

  const handleSettle = (e: React.FormEvent) => {
    e.preventDefault()
    const amount = Number(settleAmount)
    if (!Number.isFinite(amount) || amount <= 0) {
      setSettleError(t('admin.commissionAmountInvalid', 'Enter a transfer amount greater than zero.'))
      return
    }
    settleMutation.mutate({
      amount,
      note: settleNote.trim() || undefined,
      reference: settleReference.trim() || undefined,
    })
  }

  if (!targetId) {
    return (
      <div className="text-center py-12 text-slate-500">
        {t('admin.noPublisherSelected')}
      </div>
    )
  }

  if (isLoading || isLoadingGlobal) {
    return <div className="text-center py-12">{t('common.loading')}</div>
  }

  const account = commissionData?.account
  const ledgerItems: PublisherCommissionEntry[] = Array.isArray(commissionData?.ledger?.data)
    ? commissionData.ledger.data
    : []

  return (
    <div className="max-w-3xl mx-auto">
      <div className="flex items-center gap-4 mb-6">
        <Link to={isPublisherManager ? "/admin" : "/admin/publishers"} className="text-navy-700 hover:underline text-sm">
          ← {isPublisherManager ? t('admin.dashboard') : t('admin.publishers')}
        </Link>
      </div>

      <h1 className="text-2xl font-bold text-navy-900 mb-6">
        {t('admin.publisherSettings', 'Publisher Settings')}
      </h1>

      {message && (
        <div className="mb-6 p-4 bg-green-50 text-green-800 rounded-lg border border-green-200">
          {message}
        </div>
      )}
      {error && (
        <div className="mb-6 p-4 bg-red-50 text-red-800 rounded-lg border border-red-200">
          {error}
        </div>
      )}

      <section className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div className="px-6 py-4 border-b border-slate-200">
          <h2 className="text-lg font-semibold text-navy-900">
            {t('admin.commissionAccount', 'Site commission account')}
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            {t(
              'admin.commissionAccountHint',
              'On each sale, the agreed percentage is collected here. Transfer that amount to the site account, then mark the transfer below.',
            )}
          </p>
        </div>
        <div className="p-6 space-y-6">
          {isLoadingCommission ? (
            <p className="text-slate-500">{t('common.loading')}</p>
          ) : (
            <>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="rounded-lg bg-amber-50 border border-amber-100 p-4">
                  <p className="text-xs uppercase tracking-wide text-amber-800/80">
                    {t('admin.commissionOwed', 'Owed to site')}
                  </p>
                  <p className="text-2xl font-semibold text-amber-900 mt-1">
                    ${Number(account?.commission_owed ?? 0).toFixed(2)}
                  </p>
                </div>
                <div className="rounded-lg bg-slate-50 border border-slate-200 p-4">
                  <p className="text-xs uppercase tracking-wide text-slate-500">
                    {t('admin.commissionAccrued', 'Total accrued')}
                  </p>
                  <p className="text-2xl font-semibold text-slate-800 mt-1">
                    ${Number(account?.commission_accrued_total ?? 0).toFixed(2)}
                  </p>
                </div>
                <div className="rounded-lg bg-slate-50 border border-slate-200 p-4">
                  <p className="text-xs uppercase tracking-wide text-slate-500">
                    {t('admin.commissionPaid', 'Total transferred')}
                  </p>
                  <p className="text-2xl font-semibold text-slate-800 mt-1">
                    ${Number(account?.commission_paid_total ?? 0).toFixed(2)}
                  </p>
                </div>
              </div>

              <form onSubmit={handleSettle} className="space-y-4 border border-slate-200 rounded-lg p-4 bg-slate-50">
                <h3 className="text-sm font-medium text-slate-800">
                  {t('admin.markCommissionTransfer', 'Mark transfer to site')}
                </h3>
                {settleMessage && (
                  <div className="p-3 bg-green-50 text-green-800 rounded-lg border border-green-200 text-sm">
                    {settleMessage}
                  </div>
                )}
                {settleError && (
                  <div className="p-3 bg-red-50 text-red-800 rounded-lg border border-red-200 text-sm">
                    {settleError}
                  </div>
                )}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      {t('admin.transferAmount', 'Amount')}
                    </label>
                    <input
                      type="number"
                      min="0.01"
                      step="0.01"
                      value={settleAmount}
                      onChange={(e) => setSettleAmount(e.target.value)}
                      className="w-full px-4 py-2 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      {t('admin.transferReference', 'Reference')}
                    </label>
                    <input
                      type="text"
                      value={settleReference}
                      onChange={(e) => setSettleReference(e.target.value)}
                      placeholder={t('admin.transferReferencePlaceholder', 'Bank/PayPal ref (optional)')}
                      className="w-full px-4 py-2 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                      {t('admin.transferNote', 'Note')}
                    </label>
                    <input
                      type="text"
                      value={settleNote}
                      onChange={(e) => setSettleNote(e.target.value)}
                      className="w-full px-4 py-2 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
                    />
                  </div>
                </div>
                <div className="flex justify-end">
                  <button
                    type="submit"
                    disabled={settleMutation.isPending || Number(account?.commission_owed ?? 0) <= 0}
                    className="px-5 py-2.5 bg-navy-600 text-white font-medium rounded-lg hover:bg-navy-700 transition-colors disabled:opacity-50"
                  >
                    {settleMutation.isPending
                      ? t('common.saving', 'Saving...')
                      : t('admin.recordTransfer', 'Record transfer')}
                  </button>
                </div>
              </form>

              <div>
                <h3 className="text-sm font-medium text-slate-800 mb-3">
                  {t('admin.commissionLedger', 'Commission history')}
                </h3>
                {ledgerItems.length === 0 ? (
                  <p className="text-sm text-slate-500">
                    {t('admin.commissionLedgerEmpty', 'No commission entries yet.')}
                  </p>
                ) : (
                  <div className="overflow-x-auto border border-slate-200 rounded-lg">
                    <table className="min-w-full text-sm">
                      <thead className="bg-slate-50 text-slate-600">
                        <tr>
                          <th className="text-left px-3 py-2 font-medium">{t('admin.date', 'Date')}</th>
                          <th className="text-left px-3 py-2 font-medium">{t('admin.type', 'Type')}</th>
                          <th className="text-right px-3 py-2 font-medium">{t('admin.amount', 'Amount')}</th>
                          <th className="text-left px-3 py-2 font-medium">{t('admin.details', 'Details')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {ledgerItems.map((entry) => (
                          <tr key={entry._id} className="border-t border-slate-100">
                            <td className="px-3 py-2 text-slate-600 whitespace-nowrap">
                              {entry.created_at
                                ? new Date(entry.created_at).toLocaleString()
                                : '—'}
                            </td>
                            <td className="px-3 py-2">
                              {entry.type === 'settlement'
                                ? t('admin.commissionTypeSettlement', 'Transfer to site')
                                : t('admin.commissionTypeSale', 'Sale commission')}
                            </td>
                            <td className="px-3 py-2 text-right font-medium">
                              ${Number(entry.amount).toFixed(2)}
                            </td>
                            <td className="px-3 py-2 text-slate-500">
                              {entry.type === 'sale_accrual' && entry.order_id
                                ? `${t('admin.order', 'Order')}: ${entry.order_id}`
                                : [entry.reference, entry.note].filter(Boolean).join(' · ') || '—'}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      </section>

      <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div className="p-6 space-y-6">
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                {t('admin.supportEmail', 'Support Email')}
              </label>
              <input
                type="email"
                value={form.support_email}
                onChange={(e) => setForm({ ...form, support_email: e.target.value })}
                className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                {t('admin.supportPhone', 'Support Phone')}
              </label>
              <input
                type="text"
                value={form.support_phone}
                onChange={(e) => setForm({ ...form, support_phone: e.target.value })}
                className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-2">
              {t('admin.defaultDiscount', 'Default Discount (%)')}
            </label>
            <input
              type="number"
              min="0"
              max="100"
              value={form.default_discount}
              onChange={(e) => setForm({ ...form, default_discount: Number(e.target.value) })}
              className="w-full max-w-[200px] px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
            />
            <p className="text-sm text-slate-500 mt-1">
              {t('admin.defaultDiscountHint', 'This discount will be applied to all your books unless overridden.')}
            </p>
          </div>

          <div className="border-t border-slate-200 pt-6 space-y-6">
            <div>
              <h2 className="text-lg font-semibold text-navy-900">
                {t('admin.payoutAccounts', 'Payout accounts')}
              </h2>
              <p className="text-sm text-slate-500 mt-1">
                {t(
                  'admin.payoutAccountsHint',
                  'Customers pay this publishing house directly when an account is set. Project management keeps the agreed percentage of book revenue.',
                )}
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">
                  {t('admin.paypalEmail', 'PayPal email')}
                </label>
                <input
                  type="email"
                  value={form.paypal_email}
                  onChange={(e) => setForm({ ...form, paypal_email: e.target.value })}
                  className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
                  placeholder="publisher@paypal.com"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">
                  {t('admin.paypalMerchantId', 'PayPal merchant ID')}
                </label>
                <input
                  type="text"
                  value={form.paypal_merchant_id}
                  onChange={(e) => setForm({ ...form, paypal_merchant_id: e.target.value })}
                  className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
                />
                <p className="text-sm text-slate-500 mt-1">
                  {t('admin.paypalMerchantIdHint', 'Optional. Use if PayPal provided a merchant ID for this house.')}
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">
                  {t('admin.bankName', 'Bank name')}
                </label>
                <input
                  type="text"
                  value={form.bank_name}
                  onChange={(e) => setForm({ ...form, bank_name: e.target.value })}
                  className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">
                  {t('admin.bankAccountNumber', 'Bank account number')}
                </label>
                <input
                  type="text"
                  value={form.bank_account_number}
                  onChange={(e) => setForm({ ...form, bank_account_number: e.target.value })}
                  autoComplete="off"
                  className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                {t('admin.platformCommissionPercent', 'Project management commission (%)')}
              </label>
              <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={form.platform_commission_percent}
                onChange={(e) => setForm({ ...form, platform_commission_percent: Number(e.target.value) })}
                disabled={!isManager}
                className="w-full max-w-[200px] px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500 disabled:opacity-70"
              />
              <p className="text-sm text-slate-500 mt-1">
                {isManager
                  ? t(
                      'admin.platformCommissionPercentHint',
                      'Share of book revenue (not shipping) kept by project management, as agreed with this publishing house.',
                    )
                  : t(
                      'admin.platformCommissionReadOnly',
                      'This rate is set with project management and cannot be changed here.',
                    )}
              </p>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-2">
              {t('admin.returnPolicy', 'Return Policy')}
            </label>
            <textarea
              rows={4}
              value={form.return_policy}
              onChange={(e) => setForm({ ...form, return_policy: e.target.value })}
              className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
              placeholder={t('admin.returnPolicyPlaceholder', 'Describe your return policy...')}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-3">
              {t('admin.paymentMethods', 'Payment Methods')}
            </label>
            {globalPaymentMethods.length > 0 ? (
              <div className="space-y-2 border border-slate-200 rounded-lg p-4 bg-slate-50">
                {globalPaymentMethods.filter(m => m.enabled).map((method) => (
                  <label key={method.id} className="flex items-center gap-3">
                    <input
                      type="checkbox"
                      checked={form.payment_methods?.includes(method.id) || false}
                      onChange={(e) => {
                        const isChecked = e.target.checked
                        setForm((prev) => {
                          const methods = prev.payment_methods || []
                          if (isChecked) {
                            return { ...prev, payment_methods: [...methods, method.id] }
                          } else {
                            return { ...prev, payment_methods: methods.filter((id) => id !== method.id) }
                          }
                        })
                      }}
                      className="w-4 h-4 text-navy-600 rounded border-slate-300 focus:ring-navy-500"
                    />
                    <span className="text-slate-800">{method.name}</span>
                  </label>
                ))}
                {globalPaymentMethods.filter(m => m.enabled).length === 0 && (
                  <p className="text-slate-500 text-sm">
                    {t('admin.noGlobalPaymentMethods', 'No payment methods are globally enabled.')}
                  </p>
                )}
              </div>
            ) : (
              <p className="text-sm text-slate-500">{t('admin.noGlobalPaymentMethods', 'No payment methods are globally enabled.')}</p>
            )}
            <p className="text-sm text-slate-500 mt-2">
              {t('admin.publisherPaymentMethodsHint', 'Select which global payment methods you want to accept for your orders.')}
            </p>
          </div>

        </div>
        
        <div className="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end">
          <button
            type="submit"
            disabled={updateMutation.isPending}
            className="px-6 py-2.5 bg-navy-600 text-white font-medium rounded-lg hover:bg-navy-700 transition-colors disabled:opacity-50"
          >
            {updateMutation.isPending ? t('common.saving', 'Saving...') : t('common.save', 'Save Changes')}
          </button>
        </div>
      </form>
    </div>
  )
}
