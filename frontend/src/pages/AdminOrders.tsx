import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Order, type Employee } from '../lib/api'
import { Pagination } from '../components/Pagination'

const ORDER_STATUSES = [
  'pending_review',
  'confirmed',
  'preparing',
  'shipped',
  'delivered',
  'cancelled',
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
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [paymentStatusFilter, setPaymentStatusFilter] = useState<string>('')
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['admin-orders', statusFilter, paymentStatusFilter, page],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 15 }
      if (statusFilter) params.status = statusFilter
      if (paymentStatusFilter) params.payment_status = paymentStatusFilter
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
      <h1 className="text-2xl font-bold text-amber-900 mb-6">{t('admin.orders')}</h1>

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

      {isLoading ? (
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
                  <td className="px-4 py-2 font-mono text-sm">
                    {order._id?.slice(-8)}
                  </td>
                  <td className="px-4 py-2">
                    {order.customer?.name ?? order.customer_id ?? '-'}
                  </td>
                  <td className="px-4 py-2">
                    <select
                      value={order.status}
                      onChange={(e) =>
                        updateStatusMutation.mutate({
                          id: order._id,
                          status: e.target.value,
                        })
                      }
                      disabled={updateStatusMutation.isPending}
                      className="text-sm px-2 py-1 border border-stone-300 rounded bg-white"
                    >
                      {ORDER_STATUSES.map((s) => (
                        <option key={s} value={s}>
                          {t(`admin.orderStatus.${s}`)}
                        </option>
                      ))}
                    </select>
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
  const [assignTo, setAssignTo] = useState(order.employee_id ?? '')
  useEffect(() => {
    setAssignTo(order.employee_id ?? '')
  }, [order._id, order.employee_id])

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex justify-between items-start mb-6">
            <h2 className="text-xl font-bold text-amber-900">
              Order #{order._id?.slice(-8)}
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
              {order.customer?.name ?? order.customer_id ?? '-'}
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.status')}:
              </span>{' '}
              <select
                value={order.status}
                onChange={(e) => onStatusChange(e.target.value)}
                disabled={isUpdating}
                className="ml-2 px-3 py-1 border border-stone-300 rounded"
              >
                {ORDER_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {t(`admin.orderStatus.${s}`)}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.total')}:
              </span>{' '}
              ${order.total?.toFixed(2)}
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('admin.paymentStatus')}:
              </span>{' '}
              {order.payment_status ?? '-'}
              {order.payment_method && (
                <span className="text-stone-500"> ({order.payment_method})</span>
              )}
            </div>

            <div>
              <span className="text-sm font-medium text-stone-600">
                {t('checkout.shippingAddress')}:
              </span>
              <p className="mt-1 text-stone-700">
                {order.shipping_address
                  ? [
                      order.shipping_address.address,
                      order.shipping_address.city,
                      order.shipping_address.country,
                      order.shipping_address.postal_code,
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
              <ul className="mt-2 space-y-1">
                {order.items?.map((item, i) => (
                  <li key={i} className="text-stone-700">
                    {item.quantity} × ${item.price?.toFixed(2)} (book: {item.book_id?.slice(-8)})
                  </li>
                ))}
              </ul>
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
