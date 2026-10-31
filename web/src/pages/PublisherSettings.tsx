import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useParams, Link } from 'react-router-dom'
import { useAuth } from '../contexts/authContextCore'
import { api, admin, type CurrencyOption, type PublisherCommissionEntry } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { formatBookPrice } from '../lib/utils'

type OrderTotalsPeriod = 'all' | 'day' | 'month' | 'year'

function todayDate(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60_000
  return new Date(now.getTime() - offset).toISOString().slice(0, 10)
}

interface PublisherSettingsData {
  support_email?: string
  support_phone?: string
  return_policy?: string
  currency_symbol?: string
  currencies?: CurrencyOption[]
  default_currency_code?: string
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
    currency_symbol: '$',
    currencies: [{ code: 'USD', name: 'US Dollar', symbol: '$' }],
    default_currency_code: 'USD',
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
  const [settleCurrencyCode, setSettleCurrencyCode] = useState('USD')
  const [settleReference, setSettleReference] = useState('')
  const [settleNote, setSettleNote] = useState('')
  const [settleError, setSettleError] = useState('')
  const [settleMessage, setSettleMessage] = useState('')
  const [ordersPeriod, setOrdersPeriod] = useState<OrderTotalsPeriod>('day')
  const [ordersDate, setOrdersDate] = useState(todayDate)
  const [ordersPage, setOrdersPage] = useState(1)

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
  const collectionAccount = globalSettings?.data as {
    commission_collection_paypal_email?: string
    commission_collection_bank_name?: string
    commission_collection_bank_account_number?: string
  } | undefined

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

  const { data: orderTotalsData, isLoading: isLoadingOrderTotals, error: orderTotalsError } = useQuery({
    queryKey: ['publisher-order-totals', targetId, ordersPeriod, ordersDate, ordersPage],
    queryFn: async () => {
      const res = await admin.publishers.orderTotals(String(targetId), {
        period: ordersPeriod,
        ...(ordersPeriod !== 'all' ? { date: ordersDate } : {}),
        page: ordersPage,
        per_page: 25,
      })
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
        currency_symbol: data.data.currency_symbol ?? '$',
        currencies: Array.isArray(data.data.currencies) && data.data.currencies.length > 0
          ? data.data.currencies
          : [{ code: 'USD', name: 'US Dollar', symbol: data.data.currency_symbol ?? '$' }],
        default_currency_code: data.data.default_currency_code ?? 'USD',
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
      queryClient.invalidateQueries({ queryKey: ['public-currency-options'] })
      queryClient.invalidateQueries({ queryKey: ['admin-publishers'] })
      setTimeout(() => setMessage(''), 3000)
    },
    onError: (err: unknown) => {
      const error = err as { response?: { data?: { message?: string } } }
      setError(error?.response?.data?.message || t('common.error'))
      setMessage('')
    },
  })

  const settleMutation = useMutation({
    mutationFn: (payload: { amount: number; currency_code: string; note?: string; reference?: string }) =>
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
      currency_code: Object.keys(commissionData?.account?.currency_balances ?? {}).includes(settleCurrencyCode)
        ? settleCurrencyCode
        : Object.keys(commissionData?.account?.currency_balances ?? {})[0] ?? form.default_currency_code ?? 'USD',
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
  const currencyBalances = Object.entries(account?.currency_balances ?? {})
  const displayedBalances = currencyBalances.length > 0
    ? currencyBalances
    : [[form.default_currency_code ?? 'USD', {
        commission_owed: Number(account?.commission_owed ?? 0),
        commission_accrued_total: Number(account?.commission_accrued_total ?? 0),
        commission_paid_total: Number(account?.commission_paid_total ?? 0),
      }] as [string, { commission_owed: number; commission_accrued_total: number; commission_paid_total: number }]]
  const selectedSettlementCurrency = displayedBalances.some(([code]) => code === settleCurrencyCode)
    ? settleCurrencyCode
    : displayedBalances[0]?.[0] ?? 'USD'
  const selectedCurrencyBalance = displayedBalances.find(([code]) => code === selectedSettlementCurrency)?.[1]
  const ledgerItems: PublisherCommissionEntry[] = Array.isArray(commissionData?.ledger?.data)
    ? commissionData.ledger.data
    : []
  const orderTotals = orderTotalsData?.orders
  const orderTotalItems = orderTotals?.data ?? []
  const dateInputType = ordersPeriod === 'day' ? 'date' : ordersPeriod === 'month' ? 'month' : 'number'
  const dateInputValue = ordersPeriod === 'day'
    ? ordersDate
    : ordersPeriod === 'month'
      ? ordersDate.slice(0, 7)
      : ordersDate.slice(0, 4)

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
        {isPublisherManager && (
          <div className="m-6 rounded-lg border border-navy-200 bg-navy-50 p-4 space-y-2">
            <h2 className="font-semibold text-navy-900">
              {t('admin.settings.commissionCollectionAccount', 'Site commission collection account')}
            </h2>
            <p className="text-sm text-slate-600">
              {t('admin.settings.commissionCollectionAccountHint', 'Use these account details to transfer commissions owed to the site.')}
            </p>
            {collectionAccount?.commission_collection_paypal_email && (
              <p className="text-sm text-slate-700">
                {t('admin.paypalEmail', 'PayPal email')}: {collectionAccount.commission_collection_paypal_email}
              </p>
            )}
            {collectionAccount?.commission_collection_bank_name && (
              <p className="text-sm text-slate-700">
                {t('admin.bankName', 'Bank name')}: {collectionAccount.commission_collection_bank_name}
              </p>
            )}
            {collectionAccount?.commission_collection_bank_account_number && (
              <p className="text-sm text-slate-700">
                {t('admin.bankAccountNumber', 'Bank account number')}: {collectionAccount.commission_collection_bank_account_number}
              </p>
            )}
          </div>
        )}
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
                {displayedBalances.map(([code, balance]) => (
                  <div key={code} className="contents">
                    <div className="rounded-lg bg-amber-50 border border-amber-100 p-4">
                  <p className="text-xs uppercase tracking-wide text-amber-800/80">
                    {t('admin.commissionOwed', 'Owed to site')} · {code}
                  </p>
                  <p className="text-2xl font-semibold text-amber-900 mt-1">
                    {formatBookPrice(Number(balance.commission_owed), code, code)}
                  </p>
                </div>
                <div className="rounded-lg bg-slate-50 border border-slate-200 p-4">
                  <p className="text-xs uppercase tracking-wide text-slate-500">
                    {t('admin.commissionAccrued', 'Total accrued')} · {code}
                  </p>
                  <p className="text-2xl font-semibold text-slate-800 mt-1">
                    {formatBookPrice(Number(balance.commission_accrued_total), code, code)}
                  </p>
                </div>
                <div className="rounded-lg bg-slate-50 border border-slate-200 p-4">
                  <p className="text-xs uppercase tracking-wide text-slate-500">
                    {t('admin.commissionPaid', 'Total transferred')} · {code}
                  </p>
                  <p className="text-2xl font-semibold text-slate-800 mt-1">
                    {formatBookPrice(Number(balance.commission_paid_total), code, code)}
                  </p>
                </div>
                  </div>
                ))}
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
                      {t('common.currency', 'Currency')}
                    </label>
                    <select
                      value={selectedSettlementCurrency}
                      onChange={(event) => setSettleCurrencyCode(event.target.value)}
                      className="w-full px-4 py-2 bg-white border border-slate-200 rounded-lg"
                    >
                      {displayedBalances.map(([code]) => <option key={code} value={code}>{code}</option>)}
                    </select>
                  </div>
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
                    disabled={settleMutation.isPending || Number(selectedCurrencyBalance?.commission_owed ?? 0) <= 0}
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
                              {formatBookPrice(Number(entry.amount), entry.currency_code ?? 'USD', entry.currency_code ?? 'USD')}
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

      <section className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div className="px-6 py-4 border-b border-slate-200">
          <h2 className="text-lg font-semibold text-navy-900">
            {t('admin.publisherOrderTotals', 'Publisher orders')}
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            {t('admin.publisherOrderTotalsHint', 'Order totals for this publisher, filtered by date.')}
          </p>
        </div>
        <div className="p-6 space-y-4">
          <div className="flex flex-wrap items-end gap-4">
            <label className="block text-sm font-medium text-slate-700">
              {t('admin.period', 'Period')}
              <select
                value={ordersPeriod}
                onChange={(event) => {
                  setOrdersPeriod(event.target.value as OrderTotalsPeriod)
                  setOrdersPage(1)
                }}
                className="mt-1 block px-3 py-2 border border-slate-300 rounded-lg"
              >
                <option value="all">{t('admin.allDates', 'All dates')}</option>
                <option value="day">{t('admin.day', 'Day')}</option>
                <option value="month">{t('admin.month', 'Month')}</option>
                <option value="year">{t('admin.year', 'Year')}</option>
              </select>
            </label>
            {ordersPeriod !== 'all' && (
              <label className="block text-sm font-medium text-slate-700">
                {t('admin.date', 'Date')}
                <input
                  type={dateInputType}
                  min={ordersPeriod === 'year' ? 2000 : undefined}
                  max={ordersPeriod === 'year' ? 2100 : undefined}
                  value={dateInputValue}
                  onChange={(event) => {
                    const value = event.target.value
                    if (!value) return
                    setOrdersDate(ordersPeriod === 'month' ? `${value}-01` : ordersPeriod === 'year' ? `${value}-01-01` : value)
                    setOrdersPage(1)
                  }}
                  className="mt-1 block px-3 py-2 border border-slate-300 rounded-lg"
                />
              </label>
            )}
          </div>

          {orderTotalsError && (
            <p role="alert" className="text-sm text-red-700">
              {t('admin.publisherOrderTotalsFailed', 'Could not load publisher order totals. Please try again.')}
            </p>
          )}
          {isLoadingOrderTotals ? (
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          ) : (
            <>
              <div className="overflow-x-auto border border-slate-200 rounded-lg">
                <table className="min-w-full text-sm">
                  <thead className="bg-slate-50 text-slate-600">
                    <tr>
                      <th className="text-left px-3 py-2 font-medium">{t('admin.date', 'Date')}</th>
                      <th className="text-right px-3 py-2 font-medium">{t('admin.total', 'Total')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orderTotalItems.length > 0 ? orderTotalItems.map((order, index) => (
                      <tr key={`${order.created_at ?? index}-${index}`} className="border-t border-slate-100">
                        <td className="px-3 py-2 text-slate-600">
                          {order.created_at ? new Date(order.created_at).toLocaleString() : '—'}
                        </td>
                        <td className="px-3 py-2 text-right font-medium">{formatBookPrice(Number(order.total), order.currency_code, order.currency_code)}</td>
                      </tr>
                    )) : (
                      <tr>
                        <td colSpan={2} className="px-3 py-4 text-center text-slate-500">
                          {t('admin.publisherOrderTotalsEmpty', 'No orders found for this period.')}
                        </td>
                      </tr>
                    )}
                  </tbody>
                  <tfoot className="border-t border-slate-200 bg-slate-50">
                    <tr>
                      <th className="px-3 py-3 text-left font-semibold text-slate-800">
                        {t('admin.periodTotal', 'Period total')}
                      </th>
                      <td className="px-3 py-3 text-right font-semibold text-slate-900">
                        {Object.entries(orderTotalsData?.totals_by_currency ?? {})
                          .map(([code, amount]) => formatBookPrice(Number(amount), code, code))
                          .join(' · ') || '—'}
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              {orderTotals && orderTotals.last_page > 1 && (
                <Pagination
                  currentPage={orderTotals.current_page}
                  lastPage={orderTotals.last_page}
                  total={orderTotals.total}
                  perPage={orderTotals.per_page}
                  onPageChange={setOrdersPage}
                />
              )}
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
              disabled={!isPublisherManager}
              className="w-full max-w-[200px] px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500 disabled:opacity-70"
            />
            <p className="text-sm text-slate-500 mt-1">
              {isPublisherManager
                ? t('admin.defaultDiscountHint', 'This discount will be applied to all your books unless overridden.')
                : t('admin.defaultDiscountReadOnly', 'Only the publishing house manager can change this discount.')}
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-2">
              {t('admin.currencySymbol', 'Book price currency symbol')}
            </label>
            <input
              type="text"
              maxLength={8}
              value={form.currency_symbol}
              onChange={(e) => setForm({ ...form, currency_symbol: e.target.value })}
              className="w-full max-w-[200px] px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500"
            />
            <p className="text-sm text-slate-500 mt-1">
              {t('admin.currencySymbolHint', 'For example $, €, or JD. This changes the displayed symbol only; prices are not converted.')}
            </p>
          </div>

          <div className="space-y-4 rounded-lg border border-slate-200 p-4">
            <div>
              <h2 className="font-semibold text-slate-800">{t('admin.currencies', 'Supported currencies')}</h2>
              <p className="mt-1 text-sm text-slate-500">
                {t('admin.currenciesHint', 'Set the currencies customers may choose and enter a separate price for each book. No exchange-rate conversion is used.')}
              </p>
            </div>
            <label className="block max-w-sm text-sm font-medium text-slate-700">
              {t('admin.defaultCurrency', 'Default currency for existing book prices')}
              <select
                value={form.default_currency_code ?? 'USD'}
                onChange={(event) => setForm({ ...form, default_currency_code: event.target.value })}
                disabled={!isPublisherManager}
                className="mt-1 w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg disabled:opacity-70"
              >
                {(form.currencies ?? []).map((currency) => (
                  <option key={currency.code} value={currency.code}>{currency.code} — {currency.name}</option>
                ))}
              </select>
            </label>
            <div className="space-y-3">
              {(form.currencies ?? []).map((currency, index) => (
                <div key={`${currency.code}-${index}`} className="space-y-2">
                  <div className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-3">
                    <input
                      aria-label={t('admin.currencyCode', 'Currency code')}
                      value={currency.code}
                      onChange={(event) => setForm((previous) => ({
                        ...previous,
                        currencies: (previous.currencies ?? []).map((item, itemIndex) =>
                          itemIndex === index ? { ...item, code: event.target.value.toUpperCase() } : item),
                      }))}
                      disabled={!isPublisherManager}
                      maxLength={3}
                      placeholder="USD"
                      className="min-w-0 w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg disabled:opacity-70"
                    />
                    <input
                      aria-label={t('admin.currencyName', 'Currency name')}
                      value={currency.name}
                      onChange={(event) => setForm((previous) => ({
                        ...previous,
                        currencies: (previous.currencies ?? []).map((item, itemIndex) =>
                          itemIndex === index ? { ...item, name: event.target.value } : item),
                      }))}
                      disabled={!isPublisherManager}
                      placeholder={t('admin.currencyName', 'Currency name')}
                      className="min-w-0 w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg disabled:opacity-70"
                    />
                    <input
                      aria-label={t('admin.currencySymbol', 'Currency symbol')}
                      value={currency.symbol}
                      onChange={(event) => setForm((previous) => ({
                        ...previous,
                        currencies: (previous.currencies ?? []).map((item, itemIndex) =>
                          itemIndex === index ? { ...item, symbol: event.target.value } : item),
                      }))}
                      disabled={!isPublisherManager}
                      placeholder="$"
                      className="min-w-0 w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg disabled:opacity-70"
                    />
                  </div>
                  {isPublisherManager && (
                    <button
                      type="button"
                      onClick={() => setForm((previous) => {
                        const currencies = (previous.currencies ?? []).filter((_, itemIndex) => itemIndex !== index)
                        return {
                          ...previous,
                          currencies,
                          default_currency_code: currencies.some((item) => item.code === previous.default_currency_code)
                            ? previous.default_currency_code
                            : (currencies[0]?.code ?? ''),
                        }
                      })}
                      className="px-3 py-2 text-red-700 border border-red-200 rounded-lg hover:bg-red-50"
                    >
                      {t('common.remove', 'Remove')}
                    </button>
                  )}
                </div>
              ))}
            </div>
            {isPublisherManager && (
              <button
                type="button"
                onClick={() => setForm((previous) => ({
                  ...previous,
                  currencies: [...(previous.currencies ?? []), { code: '', name: '', symbol: '' }],
                }))}
                className="px-3 py-2 text-sm font-medium text-navy-700 border border-navy-200 rounded-lg hover:bg-navy-50"
              >
                {t('admin.addCurrency', 'Add currency')}
              </button>
            )}
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
              {!isPublisherManager && (
                <p className="text-sm text-slate-500 mt-1">
                  {t('admin.payoutAccountsReadOnly', 'Only the publishing house manager can edit these payout accounts.')}
                </p>
              )}
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
                  disabled={!isPublisherManager}
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
                  disabled={!isPublisherManager}
                  className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500 disabled:opacity-70"
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
                  disabled={!isPublisherManager}
                  className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500 disabled:opacity-70"
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
                  disabled={!isPublisherManager}
                  className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-500 disabled:opacity-70"
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
