import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { orders } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

export function OrderDetail() {
  const { id } = useParams<{ id: string }>()
  const { t } = useTranslation()
  const { userType } = useAuth()

  const { data, isLoading, error } = useQuery({
    queryKey: ['order', id],
    queryFn: async () => {
      const res = await orders.get(id!)
      return res.data
    },
    enabled: !!id && userType === 'customer',
  })

  if (userType !== 'customer') return null
  if (isLoading) return <div className="text-center py-12">{t('common.loading')}</div>
  if (error || !data?.data) {
    return (
      <div className="text-center py-12">
        <p className="text-red-600">{t('orders.notFound')}</p>
        <Link to="/orders" className="mt-4 inline-block text-amber-700 hover:underline">
          {t('orders.backToList')}
        </Link>
      </div>
    )
  }

  const order = data.data as {
    _id: string
    status: string
    total: number
    payment_method?: string
    payment_status?: string
    shipping_address?: { address?: string; city?: string; country?: string; postal_code?: string }
    items: { book_id: string; quantity: number; price: number }[]
    created_at?: string
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6">
        <Link to="/orders" className="text-amber-700 hover:underline text-sm">
          ← {t('orders.backToList')}
        </Link>
      </div>
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">
        {t('orders.order')} #{order._id?.slice(-8)}
      </h1>
      <div className="bg-white rounded-lg border border-stone-200 p-6 space-y-4">
        <div>
          <span className="text-sm font-medium text-stone-600">{t('admin.status')}:</span>{' '}
          <span className="text-stone-800">{order.status}</span>
        </div>
        <div>
          <span className="text-sm font-medium text-stone-600">{t('orders.total')}:</span>{' '}
          <span className="text-stone-800">${order.total?.toFixed(2)}</span>
        </div>
        <div>
          <span className="text-sm font-medium text-stone-600">{t('checkout.paymentMethod')}:</span>{' '}
          <span className="text-stone-800">{order.payment_method ?? '-'}</span>
        </div>
        <div>
          <span className="text-sm font-medium text-stone-600">{t('orders.paymentStatus')}:</span>{' '}
          <span className="text-stone-800">{order.payment_status ?? '-'}</span>
        </div>
        {order.shipping_address && (
          <div>
            <span className="text-sm font-medium text-stone-600">{t('checkout.shippingAddress')}:</span>
            <p className="mt-1 text-stone-700">
              {[
                order.shipping_address.address,
                order.shipping_address.city,
                order.shipping_address.country,
                order.shipping_address.postal_code,
              ]
                .filter(Boolean)
                .join(', ')}
            </p>
          </div>
        )}
        <div>
          <span className="text-sm font-medium text-stone-600">{t('admin.items')}:</span>
          <ul className="mt-2 space-y-1">
            {order.items?.map((item, i) => (
              <li key={i} className="text-stone-700">
                {item.quantity} × ${item.price?.toFixed(2)}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  )
}
