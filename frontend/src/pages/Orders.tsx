import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { orders } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'
import { Pagination } from '../components/Pagination'

export function Orders() {
  const { userType } = useAuth()
  const { t } = useTranslation()
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['orders', page],
    queryFn: async () => {
      const res = await orders.list({ page, per_page: 15 })
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
        {items.map((order: { _id: string; status: string; total: number; payment_status?: string; payment_method?: string; created_at?: string }) => (
          <Link
            key={order._id}
            to={`/orders/${order._id}`}
            className="block bg-white p-4 rounded-lg border border-stone-200 hover:border-amber-300 transition-colors"
          >
            <div className="flex justify-between items-center">
              <div>
                <p className="font-medium">Order #{order._id?.slice(-6)}</p>
                <p className="text-sm text-stone-500">
                  ${order.total?.toFixed(2)} · {order.status}
                  {order.payment_status && (
                    <span className="ml-2 text-stone-600">
                      · Payment: {order.payment_status}
                    </span>
                  )}
                </p>
              </div>
              <span className="text-stone-400">→</span>
            </div>
          </Link>
        ))}
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-12">{t('orders.noOrders')}</p>
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
