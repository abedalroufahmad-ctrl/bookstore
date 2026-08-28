import { useParams, Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { orders } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

type OrderPayload = {
  _id: string
  status: string
  total: number
  books_subtotal?: number
  shipping_fee?: number
  shipping_method?: string | null
  payment_method?: string
  payment_status?: string
  shipping_address?: { address?: string; city?: string; country?: string; postal_code?: string }
  warehouse?: {
    _id: string
    name: string
    publisher?: {
      _id: string
      name: string
    }
  }
  items: { book_id: string; quantity: number; price: number; book_title?: string }[]
  created_at?: string
}

export function OrderDetail() {
  const { id } = useParams<{ id: string }>()
  const { t } = useTranslation()
  const { userType } = useAuth()
  const queryClient = useQueryClient()

  const { data, isLoading, error } = useQuery({
    queryKey: ['order', id],
    queryFn: async () => {
      const res = await orders.get(id!)
      return res.data
    },
    enabled: !!id && userType === 'customer',
  })

  const confirmMut = useMutation({
    mutationFn: () => orders.confirmQuote(id!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['order', id] })
      queryClient.invalidateQueries({ queryKey: ['orders'] })
    },
  })

  const paypalMut = useMutation({
    mutationFn: () => orders.paypalStartQuoted([id!]),
    onSuccess: async (axiosRes) => {
      const payload = axiosRes.data
      if (payload.success && payload.data?.approval_url) {
        queryClient.invalidateQueries({ queryKey: ['order', id] })
        queryClient.invalidateQueries({ queryKey: ['orders'] })
        window.location.href = payload.data.approval_url
      }
    },
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

  const order = data.data as OrderPayload
  const canConfirmCod =
    order.status === 'awaiting_customer_confirmation' && order.payment_method !== 'paypal'
  const canPayPal =
    order.status === 'awaiting_customer_confirmation' && order.payment_method === 'paypal'
  const actionError = confirmMut.error ?? paypalMut.error

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-6">
        <Link to="/orders" className="text-amber-700 hover:underline text-sm">
          ← {t('orders.backToList')}
        </Link>
      </div>
      <h1 className="font-mono font-bold text-[var(--color-text)] mb-6 break-all text-xl sm:text-2xl">
        {t('orders.order')} #{order._id}
      </h1>
      <div className="bg-white rounded-lg border border-stone-200 p-6 space-y-4">
        <div>
          <span className="text-sm font-medium text-stone-600">{t('admin.status')}:</span>{' '}
          <span className="text-stone-800">
            {t(`admin.orderStatus.${order.status}`, order.status)}
          </span>
        </div>
        {order.warehouse && (
          <>
            <div>
              <span className="text-sm font-medium text-stone-600">{t('admin.publisher', 'Publisher')}:</span>{' '}
              <span className="text-stone-800">
                {order.warehouse.publisher?.name ?? '-'}
              </span>
            </div>
            <div>
              <span className="text-sm font-medium text-stone-600">{t('admin.warehouse', 'Warehouse')}:</span>{' '}
              <span className="text-stone-800">
                {order.warehouse.name}
              </span>
            </div>
          </>
        )}
        <div className="space-y-1">
          {(order.books_subtotal != null || order.shipping_fee != null) && (
            <>
              <div className="text-sm text-stone-600 flex justify-between">
                <span>{t('orders.booksSubtotal', 'Books subtotal')}</span>
                <span>${(order.books_subtotal ?? 0).toFixed(2)}</span>
              </div>
              <div className="text-sm text-stone-600 flex justify-between">
                <span>{t('orders.shippingFee', 'Shipping fee')}</span>
                <span>${(order.shipping_fee ?? 0).toFixed(2)}</span>
              </div>
            </>
          )}
          <div>
            <span className="text-sm font-medium text-stone-600">{t('orders.total')}:</span>{' '}
            <span className="text-stone-800">${order.total?.toFixed(2)}</span>
          </div>
        </div>
        {order.shipping_method ? (
          <div>
            <span className="text-sm font-medium text-stone-600">
              {t('orders.shippingMethod', 'Shipping method')}:
            </span>{' '}
            <span className="text-stone-800">{order.shipping_method}</span>
          </div>
        ) : null}
        <div>
          <span className="text-sm font-medium text-stone-600">{t('checkout.paymentMethod')}:</span>{' '}
          <span className="text-stone-800">{order.payment_method ?? '-'}</span>
        </div>
        <div>
          <span className="text-sm font-medium text-stone-600">{t('orders.paymentStatus')}:</span>{' '}
          <span className="text-stone-800">{order.payment_status ?? '-'}</span>
        </div>
        {order.status === 'awaiting_customer_confirmation' && (
          <p className="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-3">
            {t(
              'orders.awaitingConfirmationHint',
              'The warehouse finalized this quote. Confirm to send it back for fulfillment, or pay with PayPal if that is your selected method.'
            )}
          </p>
        )}
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
          <div className="mt-2 overflow-x-auto rounded-lg border border-stone-200">
            <table className="w-full table-fixed border-collapse text-sm">
              <thead>
                <tr className="bg-stone-100 border-b border-stone-200">
                  <th
                    scope="col"
                    className="min-w-0 w-[62%] px-3 py-2 font-medium text-stone-700 align-top text-start"
                  >
                    {t('orders.itemTitleCol')}
                  </th>
                  <th
                    scope="col"
                    className="w-[38%] px-3 py-2 font-medium text-stone-700 align-top text-end whitespace-nowrap"
                  >
                    {t('orders.itemPriceCol')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {order.items?.map((item, i) => {
                  const title =
                    item.book_title ??
                    (item.book_id ? `${t('common.book')} (${item.book_id.slice(-8)})` : '—')
                  const linePrice = `${item.quantity} × $${item.price?.toFixed(2)}`
                  return (
                    <tr
                      key={i}
                      className={`text-stone-800 align-top border-b border-stone-100 ${i % 2 === 1 ? 'bg-stone-50/80' : 'bg-white'}`}
                    >
                      <td className="min-w-0 px-3 py-2 break-words whitespace-normal align-top font-medium">
                        {title}
                      </td>
                      <td className="px-3 py-2 text-end whitespace-nowrap tabular-nums align-top">
                        <span dir="ltr" className="inline-block">
                          {linePrice}
                        </span>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>

        {(canConfirmCod || canPayPal) && (
          <div className="pt-4 border-t border-stone-200 space-y-2">
            {canConfirmCod && (
              <button
                type="button"
                disabled={confirmMut.isPending}
                className="w-full py-2.5 rounded-lg font-medium text-white bg-[var(--color-primary)] hover:opacity-90 disabled:opacity-50"
                onClick={() => confirmMut.mutate()}
              >
                {confirmMut.isPending ? t('common.loading') : t('orders.confirmWithWarehouse', 'Confirm & resubmit to warehouse')}
              </button>
            )}
            {canPayPal && (
              <button
                type="button"
                disabled={paypalMut.isPending}
                className="w-full py-2.5 rounded-lg font-medium text-white bg-[#00457C] hover:opacity-90 disabled:opacity-50"
                onClick={() => paypalMut.mutate()}
              >
                {paypalMut.isPending ? t('common.loading') : t('orders.payWithPayPal', 'Pay with PayPal')}
              </button>
            )}
          </div>
        )}

        {actionError && (
          <p className="text-red-600 text-sm">
            {(actionError as { response?: { data?: { message?: string } } }).response?.data?.message ??
              (actionError as Error).message}
          </p>
        )}
      </div>
    </div>
  )
}
