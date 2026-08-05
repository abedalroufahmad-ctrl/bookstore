import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Order, type Employee } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { AdminListSearchBar } from '../components/AdminListSearchBar'
import { useSearchCommit } from '../hooks/useSearchCommit'

const ORDER_STATUSES = [
  'pending_warehouse_review',
  'awaiting_customer_confirmation',
  'resubmitted_to_warehouse',
  'processing_fulfillment',
  'shipped_collecting_payment',
  'completed',
  'cancelled',
  'pending_review',
  'confirmed',
  'preparing',
  'shipped',
  'delivered',
]

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return Array.isArray(d) ? d : []
}

function formatDate(s?: string) {
  if (!s) return '-'
  try {
    return new Date(s).toLocaleDateString()
  } catch {
    return s
  }
}

export function AdminOrders() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [paymentStatusFilter, setPaymentStatusFilter] = useState<string>('')
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null)

  useEffect(() => {
    setPage(1)
  }, [committedSearch, statusFilter, paymentStatusFilter])

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['admin-orders', statusFilter, paymentStatusFilter, page, committedSearch],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 25 }
      if (statusFilter) params.status = statusFilter
      if (paymentStatusFilter) params.payment_status = paymentStatusFilter
      if (committedSearch) params.search = committedSearch
      const res = await admin.orders.list(params)
      return res.data
    },
  })

  const { data: employeesData } = useQuery({
    queryKey: ['admin-employees'],
    queryFn: async () => {
      const res = await admin.employees.list({ per_page: 100 })
      return res.data
    },
  })

  const updateStatusMutation = useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      admin.orders.updateStatus(id, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-orders'] })
      if (selectedOrder) {
        queryClient.invalidateQueries({
          queryKey: ['admin-order', selectedOrder._id],
        })
        setSelectedOrder(null)
      }
    },
  })

  const assignMutation = useMutation({
    mutationFn: ({ id, employeeId }: { id: string; employeeId: string }) =>
      admin.orders.assign(id, employeeId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-orders'] })
      if (selectedOrder) {
        queryClient.invalidateQueries({
          queryKey: ['admin-order', selectedOrder._id],
        })
        setSelectedOrder(null)
      }
    },
  })

  const ordersPaginated = data?.data
  const orders = ordersPaginated?.data ?? extractList<Order>(data)
  const ordersMeta = ordersPaginated && 'current_page' in ordersPaginated ? ordersPaginated : null
  const employees = extractList<Employee>(employeesData)

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-4">{t('admin.orders')}</h1>

      <AdminListSearchBar
        value={searchInput}
        onChange={setSearchInput}
        placeholder={t('admin.searchOrdersPlaceholder')}
        hint={t('admin.listAutoSearchHint')}
        isFetching={isFetching}
        committedValue={committedSearch}
        onCommit={commitSearch}
        className="mb-4"
      />

      <div className="mb-4 flex flex-wrap gap-4 items-center">
        <div className="flex items-center gap-2">
          <label className="text-sm font-medium text-stone-700">{t('admin.filterByStatus')}</label>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          >
            <option value="">{t('admin.all')}</option>
            {ORDER_STATUSES.map((s) => (
              <option key={s} value={s}>
                {t(`admin.orderStatus.${s}`)}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <label className="text-sm font-medium text-stone-700">{t('admin.filterByPaymentStatus')}</label>
          <select
            value={paymentStatusFilter}
            onChange={(e) => setPaymentStatusFilter(e.target.value)}
            className="px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          >
            <option value="">{t('admin.all')}</option>
            <option value="pending">{t('admin.paymentPending')}</option>
            <option value="paid">{t('admin.paymentPaid')}</option>
            <option value="failed">{t('admin.paymentFailed')}</option>
          </select>
        </div>
      </div>

      {isLoading && !data ? (
        <div className="text-center py-12">{t('common.loading')}</div>
      ) : (
        <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
          <table className="w-full">
            <thead className="bg-stone-100">
              <tr>
                <th className="px-4 py-2 text-left">{t('admin.order')}</th>
                <th className="px-4 py-2 text-left">{t('admin.customer')}</th>
                <th className="px-4 py-2 text-left">{t('admin.status')}</th>
                <th className="px-4 py-2 text-left">{t('admin.paymentStatus')}</th>
                <th className="px-4 py-2 text-left">{t('admin.total')}</th>
                <th className="px-4 py-2 text-left">{t('admin.assignedTo')}</th>
                <th className="px-4 py-2 text-left">{t('admin.date')}</th>
                <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order._id} className="border-t border-stone-200">
                  <td className="px-4 py-2 font-mono text-xs break-all max-w-[14rem]">
                    {order._id}
                  </td>
                  <td className="px-4 py-2">
                    {order.customer?.name ?? order.customer_id ?? '-'}
                  </td>
                  <td className="px-4 py-2">
                    {t(`admin.orderStatus.${order.status}`, order.status)}
                  </td>
                  <td className="px-4 py-2">{order.payment_status ?? '-'}</td>
                  <td className="px-4 py-2">${order.total?.toFixed(2)}</td>
                  <td className="px-4 py-2">
                    {order.employee?.name ?? order.employee_id ?? (
                      <span className="text-stone-500">{t('admin.unassigned')}</span>
                    )}
                  </td>
                  <td className="px-4 py-2">{formatDate(order.created_at)}</td>
                  <td className="px-4 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => setSelectedOrder(order)}
                      className="text-amber-700 hover:underline text-sm"
                    >
                      {t('admin.view')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {orders.length === 0 && !isLoading && (
        <p className="text-center text-stone-500 py-8">{t('admin.noOrders')}</p>
      )}
      {ordersMeta && (
        <Pagination
          currentPage={ordersMeta.current_page}
          lastPage={ordersMeta.last_page}
          total={ordersMeta.total}
          perPage={ordersMeta.per_page}
          onPageChange={setPage}
        />
      )}

      {selectedOrder && (
        <OrderDetailModal
          order={selectedOrder}
          employees={employees}
          onClose={() => setSelectedOrder(null)}
          onAssign={(employeeId) =>
            assignMutation.mutate({
              id: selectedOrder._id,
              employeeId,
            })
          }
          onStatusChange={(status) =>
            updateStatusMutation.mutate({
              id: selectedOrder._id,
              status,
            })
          }
          isAssigning={assignMutation.isPending}
          isUpdating={updateStatusMutation.isPending}
        />
      )}
    </div>
  )
}

function OrderDetailModal({
  order,
  employees,
  onClose,
  onAssign,
  onStatusChange,
  isAssigning,
  isUpdating,
}: {
  order: Order
  employees: Employee[]
  onClose: () => void
  onAssign: (employeeId: string) => void
  onStatusChange: (status: string) => void
  isAssigning: boolean
  isUpdating: boolean
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { data: detailOrder, isLoading: detailLoading } = useQuery({
    queryKey: ['admin-order', order._id],
    queryFn: async () => {
      const res = await admin.orders.get(order._id)
      const body = res.data
      if (!body.success || !body.data) {
        throw new Error(body.message || 'Failed to load order')
      }
      return body.data
    },
  })

  const displayOrder = detailOrder ?? order

  const [assignTo, setAssignTo] = useState(order.employee_id ?? '')
  const [shippingFeeInput, setShippingFeeInput] = useState('')
  const [shippingMethodInput, setShippingMethodInput] = useState('')
  const [confirmPaymentMethod, setConfirmPaymentMethod] = useState(order.payment_method ?? '')

  useEffect(() => {
    setAssignTo(displayOrder.employee?._id ?? displayOrder.employee_id ?? '')
  }, [displayOrder._id, displayOrder.employee_id, displayOrder.employee?._id])

  useEffect(() => {
    setShippingFeeInput(String(displayOrder.shipping_fee ?? 0))
    setShippingMethodInput(displayOrder.shipping_method ?? '')
    setConfirmPaymentMethod(displayOrder.payment_method ?? '')
  }, [
    displayOrder._id,
    displayOrder.shipping_fee,
    displayOrder.shipping_method,
    displayOrder.payment_method,
  ])


  const needsWarehouseQuote =
    displayOrder.status === 'pending_warehouse_review' || displayOrder.status === 'pending_review'

  const quoteMutation = useMutation({
    mutationFn: (body: { shipping_fee: number; shipping_method?: string; payment_method?: string }) =>
      admin.orders.submitWarehouseQuote(order._id, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-orders'] })
      queryClient.invalidateQueries({ queryKey: ['admin-order', order._id] })
      onClose()
    },
  })

  const submitQuote = () => {
    const fee = parseFloat(shippingFeeInput)
    if (Number.isNaN(fee) || fee < 0) {
      return
    }
    quoteMutation.mutate({
      shipping_fee: fee,
      shipping_method: shippingMethodInput.trim() || undefined,
      payment_method: confirmPaymentMethod.trim() || undefined,
    })
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex justify-between items-start mb-6">
            <h2 className="text-xl font-bold text-amber-900 font-mono text-base sm:text-lg break-all pr-2">
              Order #{order._id}
            </h2>
            <button
              type="button"
              onClick={onClose}
              className="text-stone-500 hover:text-stone-700 text-2xl"
            >
              ×
            </button>
          </div>

          <div className="space-y-4">
            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.customer')}:
              </span>{' '}
              {displayOrder.customer?.name ?? displayOrder.customer_id ?? '-'}
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.status')}:
              </span>{' '}
              <select
                value={displayOrder.status}
                onChange={(e) => onStatusChange(e.target.value)}
                disabled={isUpdating}
                className="ml-2 px-3 py-1 border border-stone-300 rounded"
              >
                {!ORDER_STATUSES.includes(displayOrder.status) && (
                  <option value={displayOrder.status}>{displayOrder.status}</option>
                )}
                {ORDER_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {t(`admin.orderStatus.${s}`, s)}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.total')}:
              </span>{' '}
              ${displayOrder.total?.toFixed(2)}
              {displayOrder.books_subtotal != null && (
                <span className="block text-xs text-stone-500 mt-1">
                  {t('orders.booksSubtotal', 'Books')}: ${displayOrder.books_subtotal.toFixed(2)} +{' '}
                  {t('orders.shippingFee', 'Shipping')}: ${(displayOrder.shipping_fee ?? 0).toFixed(2)}
                </span>
              )}
            </div>

            {needsWarehouseQuote && (
              <div className="border border-amber-200 bg-amber-50/60 rounded-lg p-4 space-y-3">
                <h3 className="font-medium text-amber-900">{t('admin.warehouseQuote', 'Warehouse quote')}</h3>
                <p className="text-sm text-stone-600">
                  {t(
                    'admin.warehouseQuoteHint',
                    'Confirm line totals, add shipping fee, and save to send the quote to the customer.',
                  )}
                </p>
                <div>
                  <label className="block text-sm text-stone-700 mb-1">
                    {t('orders.shippingFee', 'Shipping fee')}
                  </label>
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={shippingFeeInput}
                    onChange={(e) => setShippingFeeInput(e.target.value)}
                    className="w-full px-3 py-2 border border-stone-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-sm text-stone-700 mb-1">
                    {t('orders.shippingMethod', 'Shipping method')}
                  </label>
                  <input
                    type="text"
                    value={shippingMethodInput}
                    onChange={(e) => setShippingMethodInput(e.target.value)}
                    className="w-full px-3 py-2 border border-stone-300 rounded-lg"
                    placeholder={t('admin.shippingMethodPlaceholder', 'e.g. Standard courier')}
                  />
                </div>
                <div>
                  <label className="block text-sm text-stone-700 mb-1">{t('checkout.paymentMethod')}</label>
                  <input
                    type="text"
                    value={confirmPaymentMethod}
                    onChange={(e) => setConfirmPaymentMethod(e.target.value)}
                    className="w-full px-3 py-2 border border-stone-300 rounded-lg"
                    placeholder="cod / paypal / stripe …"
                  />
                </div>
                {quoteMutation.error && (
                  <p className="text-red-600 text-sm">
                    {(quoteMutation.error as { response?: { data?: { message?: string } } }).response?.data
                      ?.message ?? (quoteMutation.error as Error).message}
                  </p>
                )}
                <button
                  type="button"
                  onClick={submitQuote}
                  disabled={quoteMutation.isPending}
                  className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
                >
                  {quoteMutation.isPending ? t('common.loading') : t('admin.sendQuoteToCustomer', 'Save quote')}
                </button>
              </div>
            )}

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.paymentStatus')}:
              </span>{' '}
              {displayOrder.payment_status ?? '-'}
              {displayOrder.payment_method && (
                <span className="text-stone-500"> ({displayOrder.payment_method})</span>
              )}
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('checkout.shippingAddress')}:
              </span>
              <p className="mt-1 text-stone-700">
                {displayOrder.shipping_address
                  ? [
                      displayOrder.shipping_address.address,
                      displayOrder.shipping_address.city,
                      displayOrder.shipping_address.country,
                      displayOrder.shipping_address.postal_code,
                    ]
                      .filter(Boolean)
                      .join(', ')
                  : '-'}
              </p>
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.assignTo')}:
              </span>
              <div className="mt-2 flex gap-2">
                <select
                  value={assignTo}
                  onChange={(e) => setAssignTo(e.target.value)}
                  className="flex-1 px-3 py-2 border border-stone-300 rounded-lg"
                >
                  <option value="">{t('admin.selectEmployee')}</option>
                  {employees.map((e) => (
                    <option key={e._id} value={e._id}>
                      {e.name} ({e.role})
                    </option>
                  ))}
                </select>
                <button
                  type="button"
                  onClick={() => assignTo && onAssign(assignTo)}
                  disabled={!assignTo || isAssigning}
                  className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
                >
                  {t('admin.assign')}
                </button>
              </div>
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.items')}:
              </span>
              <div className="mt-2 overflow-x-auto rounded-lg border border-stone-200">
                <table className="w-full table-fixed border-collapse text-sm">
                  <thead>
                    <tr className="bg-stone-100 border-b border-stone-200">
                      <th className="min-w-0 w-[62%] px-3 py-2 font-medium text-stone-700 align-top text-start">
                        {t('orders.itemTitleCol')}
                      </th>
                      <th className="w-[38%] px-3 py-2 font-medium text-stone-700 align-top text-end whitespace-nowrap">
                        {t('orders.itemPriceCol')}
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-stone-100 bg-white">
                    {detailLoading ? (
                      <tr>
                        <td colSpan={2} className="px-3 py-6 text-center text-stone-500 text-sm">
                          {t('common.loading')}
                        </td>
                      </tr>
                    ) : (
                      displayOrder.items?.map((item, i) => {
                        const title =
                          item.book_title ??
                          `${t('common.book')} (${item.book_id?.slice(-8)})`
                        const linePrice = `${item.quantity} × $${item.price?.toFixed(2)}`
                        return (
                          <tr key={i} className="text-stone-800 align-top">
                            <td className="min-w-0 px-3 py-2 break-words whitespace-normal align-top font-medium">
                              {title}
                            </td>
                            <td className="px-3 py-2 text-end whitespace-nowrap tabular-nums align-top">
                              <span dir="ltr" className="inline-block max-w-full">
                                {linePrice}
                              </span>
                            </td>
                          </tr>
                        )
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div className="mt-6">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border border-stone-300 rounded-lg"
            >
              {t('admin.close')}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
