import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { orders } from '../lib/api'
import { useAuth } from '../contexts/authContextCore'
import { Pagination } from '../components/Pagination'
import { formatBookPrice, paymentStatusLabel } from '../lib/utils'

export function Orders() {
  const { userType } = useAuth()
  const { t } = useTranslation()
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['orders', page],
    queryFn: async () => {
      const res = await orders.list({ page, per_page: 25 })
      return res.data
    },
    enabled: userType === 'customer',
  })

  if (userType !== 'customer') return null

  if (isLoading) return <div className="text-center py-12">{t('common.loading')}</div>

  const paginated = data?.data
  const items = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  return (
    <div>
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">{t('orders.title')}</h1>
      <div className="space-y-4">
        {items.map((order: { _id: string; status: string; total: number; currency_code?: string; payment_status?: string; payment_method?: string; created_at?: string }) => (
          <Link
            key={order._id}
            to={`/orders/${order._id}`}
            className="block bg-white p-4 rounded-lg border border-slate-200 hover:border-navy-300 transition-colors"
          >
            <div className="flex justify-between items-center">
              <div>
                <p className="font-medium font-mono text-sm break-all">
                  {t('orders.order')} #{order._id}
                </p>
                <p className="text-sm text-slate-500">
                  {formatBookPrice(Number(order.total ?? 0), order.currency_code ?? 'USD', order.currency_code ?? 'USD')} · {t(`admin.orderStatus.${order.status}`, order.status)}
                  {order.payment_status && (
                    <span className="ml-2 text-slate-600">
                      · {t('orders.paymentStatus')}: {paymentStatusLabel(order.payment_status, t)}
                    </span>
                  )}
                </p>
              </div>
              <span className="text-slate-400">→</span>
            </div>
          </Link>
        ))}
      </div>
      {items.length === 0 && (
        <p className="text-center text-slate-500 py-12">{t('orders.noOrders')}</p>
      )}
      {meta && (
        <Pagination
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          total={meta.total}
          perPage={meta.per_page}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
