import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { admin } from '../lib/api'
import { useTranslation } from 'react-i18next'
import { Pagination } from '../components/Pagination'
import { useAuth } from '../contexts/AuthContext'

type WeightUnit = 'kg' | 'g' | 'lb' | 'oz'
type PayPalMode = 'sandbox' | 'live'
type OrderTotalsPeriod = 'day' | 'month' | 'year'

function todayDate(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60_000
  return new Date(now.getTime() - offset).toISOString().slice(0, 10)
}

type PaymentMethodItem = { id: string; name: string; enabled: boolean }

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
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [globalDiscount, setGlobalDiscount] = useState<number>(0)
  const [invoiceShippingFee, setInvoiceShippingFee] = useState<number>(0)
  const [weightUnit, setWeightUnit] = useState<WeightUnit>('kg')
  const [booksPerPage, setBooksPerPage] = useState<number>(27)
  const [booksPerRow, setBooksPerRow] = useState<number>(5)
  const [authorsPerPage, setAuthorsPerPage] = useState<number>(28)
  const [authorsPerRow, setAuthorsPerRow] = useState<number>(4)
  const [categoriesPerPage, setCategoriesPerPage] = useState<number>(28)
  const [categoriesPerRow, setCategoriesPerRow] = useState<number>(3)
  const [warehousesPerPage, setWarehousesPerPage] = useState<number>(28)
  const [warehousesPerRow, setWarehousesPerRow] = useState<number>(3)
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethodItem[]>(DEFAULT_PAYMENT_METHODS)
  const [commissionCollectionPayPalEmail, setCommissionCollectionPayPalEmail] = useState('')
  const [commissionCollectionBankName, setCommissionCollectionBankName] = useState('')
  const [commissionCollectionBankAccountNumber, setCommissionCollectionBankAccountNumber] = useState('')
  const [paypalMode, setPaypalMode] = useState<PayPalMode>('sandbox')
  const [paypalAvailableModes, setPaypalAvailableModes] = useState<PayPalMode[]>(['sandbox'])
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [editingIndex, setEditingIndex] = useState<number | null>(null)
  const [newMethodId, setNewMethodId] = useState('')
  const [newMethodName, setNewMethodName] = useState('')
  const [showAddForm, setShowAddForm] = useState(false)
  const [ordersPeriod, setOrdersPeriod] = useState<OrderTotalsPeriod>('day')
  const [ordersDate, setOrdersDate] = useState(todayDate)
  const [ordersPublisherId, setOrdersPublisherId] = useState('')
  const [ordersPage, setOrdersPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['admin-settings'],
    queryFn: async () => {
      const res = await admin.settings.get()
      return res.data
    },
  })

  const { data: orderTotalsData, isLoading: isLoadingOrderTotals, error: orderTotalsError } = useQuery({
    queryKey: ['admin-settings-order-totals', ordersPeriod, ordersDate, ordersPublisherId, ordersPage],
    enabled: user?.role === 'manager',
    queryFn: async () => {
      const res = await admin.settings.orderTotals({
        period: ordersPeriod,
        date: ordersDate,
        publisher_id: ordersPublisherId || undefined,
        page: ordersPage,
        per_page: 25,
      })
      return res.data.data
    },
  })

  useEffect(() => {
    if (data?.data) {
      const d = data.data as Record<string, unknown>
      const val = Number(d.global_discount)
      setGlobalDiscount(Number.isNaN(val) ? 0 : val)
      const shipping = Number(d.invoice_shipping_fee)
      setInvoiceShippingFee(Number.isNaN(shipping) ? 0 : Math.max(0, shipping))
      setWeightUnit((d.weight_unit as WeightUnit) || 'kg')
      const clamp = (value: unknown, fallback: number, min: number, max: number) => {
        const n = Number(value)
        return Number.isFinite(n) ? Math.min(max, Math.max(min, Math.round(n))) : fallback
      }
      setBooksPerPage(clamp(d.books_per_page ?? d.catalog_items_per_page, 27, 1, 100))
      setBooksPerRow(clamp(d.books_per_row, 5, 1, 8))
      setAuthorsPerPage(clamp(d.authors_per_page, 28, 1, 100))
      setAuthorsPerRow(clamp(d.authors_per_row, 4, 1, 8))
      setCategoriesPerPage(clamp(d.categories_per_page, 28, 1, 100))
      setCategoriesPerRow(clamp(d.categories_per_row, 3, 1, 8))
      setWarehousesPerPage(clamp(d.warehouses_per_page, 28, 1, 100))
      setWarehousesPerRow(clamp(d.warehouses_per_row, 3, 1, 8))
      setPaymentMethods(normalizePaymentMethods(d.payment_methods))
      setCommissionCollectionPayPalEmail(String(d.commission_collection_paypal_email ?? ''))
      setCommissionCollectionBankName(String(d.commission_collection_bank_name ?? ''))
      setCommissionCollectionBankAccountNumber(String(d.commission_collection_bank_account_number ?? ''))
      setPaypalMode(d.paypal_mode === 'live' ? 'live' : 'sandbox')
      setPaypalAvailableModes(
        Array.isArray(d.paypal_available_modes)
          ? d.paypal_available_modes.filter((mode): mode is PayPalMode => mode === 'sandbox' || mode === 'live')
          : []
      )
    }
  }, [data])

  const updateMutation = useMutation({
    mutationFn: (payload: {
      global_discount?: number
      invoice_shipping_fee?: number
      weight_unit?: WeightUnit
      books_per_page?: number
      books_per_row?: number
      authors_per_page?: number
      authors_per_row?: number
      categories_per_page?: number
      categories_per_row?: number
      warehouses_per_page?: number
      warehouses_per_row?: number
      payment_methods?: PaymentMethodItem[]
      paypal_mode?: PayPalMode
      commission_collection_paypal_email?: string
      commission_collection_bank_name?: string
      commission_collection_bank_account_number?: string
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
      invoice_shipping_fee: invoiceShippingFee,
      weight_unit: weightUnit,
      books_per_page: booksPerPage,
      books_per_row: booksPerRow,
      authors_per_page: authorsPerPage,
      authors_per_row: authorsPerRow,
      categories_per_page: categoriesPerPage,
      categories_per_row: categoriesPerRow,
      warehouses_per_page: warehousesPerPage,
      warehouses_per_row: warehousesPerRow,
      payment_methods: paymentMethods,
      paypal_mode: paypalAvailableModes.includes(paypalMode) ? paypalMode : undefined,
      commission_collection_paypal_email: commissionCollectionPayPalEmail,
      commission_collection_bank_name: commissionCollectionBankName,
      commission_collection_bank_account_number: commissionCollectionBankAccountNumber,
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

  const orderTotals = orderTotalsData?.orders
  const orderTotalItems = orderTotals?.data ?? []
  const dateInputType = ordersPeriod === 'day' ? 'date' : ordersPeriod === 'month' ? 'month' : 'number'
  const dateInputValue = ordersPeriod === 'day'
    ? ordersDate
    : ordersPeriod === 'month'
      ? ordersDate.slice(0, 7)
      : ordersDate.slice(0, 4)

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-bold text-navy-900 mb-6">{t('admin.settings.title') || 'Admin Settings'}</h1>

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

      {user?.role === 'manager' && <section className="mb-6 bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
        <div>
          <h2 className="text-lg font-semibold text-navy-900">
            {t('admin.publisherOrderTotals', 'Publisher orders')}
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            {t('admin.adminPublisherOrderTotalsHint', 'Order totals by publisher, filtered by date.')}
          </p>
        </div>
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
              <option value="day">{t('admin.day', 'Day')}</option>
              <option value="month">{t('admin.month', 'Month')}</option>
              <option value="year">{t('admin.year', 'Year')}</option>
            </select>
          </label>
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
          <label className="block text-sm font-medium text-slate-700">
            {t('admin.publisher', 'Publisher')}
            <select
              value={ordersPublisherId}
              onChange={(event) => {
                setOrdersPublisherId(event.target.value)
                setOrdersPage(1)
              }}
              className="mt-1 block min-w-52 px-3 py-2 border border-slate-300 rounded-lg"
            >
              <option value="">{t('admin.allPublishers', 'All publishers')}</option>
              {orderTotalsData?.publishers.map((publisher) => (
                <option key={publisher.id} value={publisher.id}>{publisher.name}</option>
              ))}
            </select>
          </label>
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
                    <th className="text-left px-3 py-2 font-medium">{t('admin.publisher', 'Publisher')}</th>
                    <th className="text-right px-3 py-2 font-medium">{t('admin.total', 'Total')}</th>
                    <th className="text-right px-3 py-2 font-medium">{t('admin.commission', 'Commission')}</th>
                  </tr>
                </thead>
                <tbody>
                  {orderTotalItems.length > 0 ? orderTotalItems.map((order, index) => (
                    <tr key={`${order.publisher_name}-${index}`} className="border-t border-slate-100">
                      <td className="px-3 py-2 text-slate-700">{order.publisher_name}</td>
                      <td className="px-3 py-2 text-right font-medium">${Number(order.total).toFixed(2)}</td>
                      <td className="px-3 py-2 text-right font-medium">${Number(order.commission).toFixed(2)}</td>
                    </tr>
                  )) : (
                    <tr>
                      <td colSpan={3} className="px-3 py-4 text-center text-slate-500">
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
                      ${Number(orderTotalsData?.total_sum ?? 0).toFixed(2)}
                    </td>
                    <td className="px-3 py-3 text-right font-semibold text-slate-900">
                      ${Number(orderTotalsData?.commission_sum ?? 0).toFixed(2)}
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
      </section>}

      <form onSubmit={handleSubmit} className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-6">
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-2">
            {t('admin.settings.globalDiscount') || 'Global Discount (%)'}
          </label>
          <div className="flex items-center gap-4">
            <input
              type="number"
              min="0"
              max="100"
              value={globalDiscount}
              onChange={(e) => setGlobalDiscount(parseFloat(e.target.value) || 0)}
              className="w-32 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
            />
            <span className="text-slate-500">%</span>
          </div>
          <p className="mt-2 text-xs text-slate-500">
            {t('admin.settings.globalDiscountHint') || 'This discount applies to all books that do not have a special discount set.'}
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-2">
            {t('admin.settings.invoiceShippingFee') || 'Invoice shipping fee ($)'}
          </label>
          <div className="flex items-center gap-4">
            <input
              type="number"
              min="0"
              step="0.01"
              value={invoiceShippingFee}
              onChange={(e) => setInvoiceShippingFee(Math.max(0, parseFloat(e.target.value) || 0))}
              className="w-32 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
            />
            <span className="text-slate-500">USD</span>
          </div>
          <p className="mt-2 text-xs text-slate-500">
            {t('admin.settings.invoiceShippingFeeHint') || 'Added to every POS invoice and new online order total.'}
          </p>
        </div>

        <div className="border border-slate-200 rounded-lg p-4 space-y-5">
          <div>
            <h2 className="text-lg font-semibold text-navy-900">
              {t('admin.settings.catalogLayout') || 'Catalog layout'}
            </h2>
            <p className="mt-1 text-xs text-slate-500">
              {t('admin.settings.catalogLayoutHint') || 'Set how many items appear on each page and how many show in each row. Narrow screens automatically use fewer columns.'}
            </p>
          </div>
          <LayoutFields
            title={t('nav.books')}
            perPage={booksPerPage}
            perRow={booksPerRow}
            onPerPage={setBooksPerPage}
            onPerRow={setBooksPerRow}
            perPageLabel={t('admin.settings.itemsPerPage') || 'Items per page'}
            perRowLabel={t('admin.settings.itemsPerRow') || 'Items per row'}
          />
          <LayoutFields
            title={t('nav.authors')}
            perPage={authorsPerPage}
            perRow={authorsPerRow}
            onPerPage={setAuthorsPerPage}
            onPerRow={setAuthorsPerRow}
            perPageLabel={t('admin.settings.itemsPerPage') || 'Items per page'}
            perRowLabel={t('admin.settings.itemsPerRow') || 'Items per row'}
          />
          <LayoutFields
            title={t('nav.categories')}
            perPage={categoriesPerPage}
            perRow={categoriesPerRow}
            onPerPage={setCategoriesPerPage}
            onPerRow={setCategoriesPerRow}
            perPageLabel={t('admin.settings.itemsPerPage') || 'Items per page'}
            perRowLabel={t('admin.settings.itemsPerRow') || 'Items per row'}
          />
          <LayoutFields
            title={t('nav.warehouses')}
            perPage={warehousesPerPage}
            perRow={warehousesPerRow}
            onPerPage={setWarehousesPerPage}
            onPerRow={setWarehousesPerRow}
            perPageLabel={t('admin.settings.itemsPerPage') || 'Items per page'}
            perRowLabel={t('admin.settings.itemsPerRow') || 'Items per row'}
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-2">
            {t('admin.settings.weightUnit') || 'Weight unit'}
          </label>
          <select
            value={weightUnit}
            onChange={(e) => setWeightUnit(e.target.value as WeightUnit)}
            className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
          >
            <option value="kg">{t('admin.settings.weightKg') || 'Kilograms (kg)'}</option>
            <option value="g">{t('admin.settings.weightG') || 'Grams (g)'}</option>
            <option value="lb">{t('admin.settings.weightLb') || 'Pounds (lb)'}</option>
            <option value="oz">{t('admin.settings.weightOz') || 'Ounces (oz)'}</option>
          </select>
          <p className="mt-2 text-xs text-slate-500">
            {t('admin.settings.weightUnitHint') || 'Book weights are stored in kg and displayed in this unit across the site.'}
          </p>
        </div>

        <div className="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
          <h2 className="text-lg font-semibold text-navy-900 mb-1">
            {t('admin.settings.paypalEnvironment') || 'PayPal environment'}
          </h2>
          <p className="text-sm text-slate-500 mb-3">
            {t('admin.settings.paypalEnvironmentHint') || 'Choose whether PayPal checkout uses test or live payments. Credentials are configured on the server.'}
          </p>
          <select
            value={paypalMode}
            onChange={(e) => setPaypalMode(e.target.value as PayPalMode)}
            className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
          >
            {paypalAvailableModes.includes('sandbox') && (
              <option value="sandbox">{t('admin.settings.paypalSandbox') || 'Sandbox (test)'}</option>
            )}
            {paypalAvailableModes.includes('live') && (
              <option value="live">{t('admin.settings.paypalLive') || 'Live (real payments)'}</option>
            )}
            {!paypalAvailableModes.includes(paypalMode) && (
              <option value={paypalMode} disabled>
                {paypalMode === 'live'
                  ? t('admin.settings.paypalLiveUnavailable') || 'Live (credentials not configured)'
                  : t('admin.settings.paypalSandboxUnavailable') || 'Sandbox (credentials not configured)'}
              </option>
            )}
          </select>
          {paypalAvailableModes.length === 1 && (
            <p className="mt-2 text-xs text-slate-500">
              {t('admin.settings.paypalOtherModeHint') || 'To enable the other environment, configure its PayPal Client ID and Secret on the server.'}
            </p>
          )}
        </div>

        <div className="border border-slate-200 rounded-lg p-4 space-y-4">
          <div>
            <h2 className="text-lg font-semibold text-navy-900">
              {t('admin.settings.commissionCollectionAccount') || 'Site commission collection account'}
            </h2>
            <p className="text-sm text-slate-500 mt-1">
              {t('admin.settings.commissionCollectionAccountHint') || 'Account details publishers use to transfer collected site commissions.'}
            </p>
          </div>
          <label className="block text-sm font-medium text-slate-700">
            {t('admin.settings.commissionCollectionPayPalEmail') || 'PayPal email'}
            <input
              type="email"
              value={commissionCollectionPayPalEmail}
              onChange={(e) => setCommissionCollectionPayPalEmail(e.target.value)}
              className="mt-1 w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
            />
          </label>
          <label className="block text-sm font-medium text-slate-700">
            {t('admin.settings.commissionCollectionBankName') || 'Bank name'}
            <input
              type="text"
              value={commissionCollectionBankName}
              onChange={(e) => setCommissionCollectionBankName(e.target.value)}
              className="mt-1 w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
            />
          </label>
          <label className="block text-sm font-medium text-slate-700">
            {t('admin.settings.commissionCollectionBankAccountNumber') || 'Bank account number'}
            <input
              type="text"
              autoComplete="off"
              value={commissionCollectionBankAccountNumber}
              onChange={(e) => setCommissionCollectionBankAccountNumber(e.target.value)}
              className="mt-1 w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
            />
          </label>
        </div>

        <div className="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
          <h2 className="text-lg font-semibold text-navy-900 mb-1">
            {t('admin.settings.managePaymentMethods') || 'Manage payment methods'}
          </h2>
          <p className="text-sm text-slate-500 mb-4">
            {t('admin.settings.paymentMethodsHint') || 'Add, edit or remove payment options. Customers see only enabled methods at checkout.'}
          </p>

          <ul className="space-y-2 mb-4">
            {paymentMethods.map((method, index) => (
              <li
                key={method.id}
                className="flex items-center gap-3 p-3 rounded-lg bg-white border border-slate-200"
              >
                {editingIndex === index ? (
                  <>
                    <input
                      type="text"
                      value={method.name}
                      onChange={(e) => updateMethod(index, { name: e.target.value })}
                      className="flex-1 px-3 py-1.5 border border-slate-300 rounded text-sm"
                      placeholder={t('admin.settings.name') || 'Display name'}
                    />
                    <label className="flex items-center gap-1.5 text-sm whitespace-nowrap">
                      <input
                        type="checkbox"
                        checked={method.enabled}
                        onChange={(e) => updateMethod(index, { enabled: e.target.checked })}
                        className="rounded border-slate-300 text-navy-900"
                      />
                      {t('admin.settings.enabled') || 'Enabled'}
                    </label>
                    <button
                      type="button"
                      onClick={() => setEditingIndex(null)}
                      className="text-sm text-slate-600 hover:text-slate-800"
                    >
                      {t('admin.close') || 'Done'}
                    </button>
                  </>
                ) : (
                  <>
                    <div className="flex-1 min-w-0">
                      <span className="font-medium text-slate-800">
                        {method.id === 'cod'
                          ? t('checkout.paymentCOD')
                          : method.id === 'stripe' || method.id === 'card'
                            ? t('admin.settings.paymentCardStripe')
                            : method.id === 'paypal'
                              ? t('checkout.paymentPayPal')
                              : method.name}
                      </span>
                      <span className="ml-2 text-xs text-slate-500 font-mono">({method.id})</span>
                    </div>
                    <span className={`text-xs px-2 py-0.5 rounded ${method.enabled ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600'}`}>
                      {method.enabled ? t('admin.settings.enabled') || 'Enabled' : t('admin.settings.disabled') || 'Disabled'}
                    </span>
                    <button
                      type="button"
                      onClick={() => setEditingIndex(index)}
                      className="text-navy-700 hover:underline text-sm"
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
            <div className="p-3 rounded-lg bg-white border border-navy-200 space-y-2">
              <input
                type="text"
                value={newMethodId}
                onChange={(e) => setNewMethodId(e.target.value)}
                placeholder={t('admin.settings.paymentMethodIdPlaceholder') || 'Method ID (e.g. bank_transfer)'}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
              />
              <input
                type="text"
                value={newMethodName}
                onChange={(e) => setNewMethodName(e.target.value)}
                placeholder={t('admin.settings.paymentMethodNamePlaceholder') || 'Display name (e.g. Bank Transfer)'}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
              />
              <div className="flex gap-2">
                <button type="button" onClick={addMethod} className="px-3 py-1.5 bg-navy-900 text-navy-50 rounded-lg text-sm">
                  {t('admin.add') || 'Add'}
                </button>
                <button type="button" onClick={() => { setShowAddForm(false); setNewMethodId(''); setNewMethodName(''); setError(''); }} className="px-3 py-1.5 border border-slate-300 rounded-lg text-sm">
                  {t('admin.cancel') || 'Cancel'}
                </button>
              </div>
            </div>
          ) : (
            <button
              type="button"
              onClick={() => setShowAddForm(true)}
              className="text-sm text-navy-700 hover:underline"
            >
              + {t('admin.settings.addPaymentMethod') || 'Add payment method'}
            </button>
          )}

          {paymentMethods.length > 0 && !paymentMethods.some((m) => m.enabled) && (
            <p className="mt-3 text-navy-700 text-sm">
              {t('admin.settings.atLeastOnePaymentMethod') || 'Enable at least one payment method so customers can checkout.'}
            </p>
          )}
        </div>

        <div className="flex items-center gap-4">
          <button
            type="submit"
            disabled={updateMutation.isPending}
            className="px-6 py-2 bg-navy-900 text-navy-50 rounded-lg hover:bg-navy-800 disabled:opacity-50"
          >
            {updateMutation.isPending ? t('common.saving') : t('common.save')}
          </button>
        </div>
      </form>
    </div>
  )
}

function LayoutFields({
  title,
  perPage,
  perRow,
  onPerPage,
  onPerRow,
  perPageLabel,
  perRowLabel,
}: {
  title: string
  perPage: number
  perRow: number
  onPerPage: (value: number) => void
  onPerRow: (value: number) => void
  perPageLabel: string
  perRowLabel: string
}) {
  return (
    <div>
      <h3 className="text-sm font-semibold text-slate-800 mb-2">{title}</h3>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <label className="block text-sm text-slate-700">
          {perPageLabel}
          <input
            type="number"
            min={1}
            max={100}
            value={perPage}
            onChange={(e) => onPerPage(Math.min(100, Math.max(1, parseInt(e.target.value, 10) || 1)))}
            className="mt-1 w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
          />
        </label>
        <label className="block text-sm text-slate-700">
          {perRowLabel}
          <input
            type="number"
            min={1}
            max={8}
            value={perRow}
            onChange={(e) => onPerRow(Math.min(8, Math.max(1, parseInt(e.target.value, 10) || 1)))}
            className="mt-1 w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy-500"
          />
        </label>
      </div>
    </div>
  )
}
