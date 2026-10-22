import { useParams, Link, useSearchParams } from 'react-router-dom'
import { useEffect } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { orders } from '../lib/api'
import { isTrustedPayPalUrl, isTrustedStripeCheckoutUrl, paymentMethodLabel, paymentStatusLabel } from '../lib/utils'
import { useAuth } from '../contexts/authContextCore'
import { useSettings } from '../contexts/settingsContextCore'

type OrderPayload = {
  _id: string
  status: string
  total: number
  books_subtotal?: number
  shipping_fee?: number
  shipping_method?: string | null
  payment_method?: string
  payment_status?: string
  payment_info?: { provider?: string; simulated?: boolean }
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
  const [searchParams] = useSearchParams()
  const { t } = useTranslation()
  const { userType } = useAuth()
  const { settings } = useSettings()
  const queryClient = useQueryClient()
  const stripeSessionId = searchParams.get('session_id')
  const stripeReturn = searchParams.get('stripe') === 'success'
  const paypalResult = searchParams.get('paypal')

  const { data, isLoading, error } = useQuery({
    queryKey: ['order', id],
    queryFn: async () => {
      const res = await orders.get(id!)
      return res.data
    },
    enabled: !!id && userType === 'customer',
  })

  const stripeVerifyQuery = useQuery({
    queryKey: ['stripe-payment-verify', id, stripeSessionId],
    queryFn: async () => {
      const res = await orders.stripeVerify(id!, stripeSessionId!)
      return res.data
    },
    enabled: !!id && userType === 'customer' && stripeReturn && !!stripeSessionId,
    retry: false,
  })

  useEffect(() => {
    if (stripeVerifyQuery.data?.success) {
      queryClient.invalidateQueries({ queryKey: ['order', id] })
      queryClient.invalidateQueries({ queryKey: ['orders'] })
    }
  }, [id, queryClient, stripeVerifyQuery.data])

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
        if (isTrustedPayPalUrl(payload.data.approval_url)) {
          window.location.assign(payload.data.approval_url)
        }
      }
    },
  })

  const paypalSimulationMut = useMutation({
    mutationFn: () => orders.paypalSimulateSandbox(id!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['order', id] })
      queryClient.invalidateQueries({ queryKey: ['orders'] })
    },
  })

  const stripeMut = useMutation({
    mutationFn: async () => {
      const res = await orders.stripeStart(id!)
      const checkoutUrl = res.data.data.checkout_url
      if (!res.data.success || !checkoutUrl || !isTrustedStripeCheckoutUrl(checkoutUrl)) {
        throw new Error(t('orders.cardPaymentStartFailed', 'Could not open secure card checkout.'))
      }
      return checkoutUrl
    },
    onSuccess: (checkoutUrl) => { window.location.assign(checkoutUrl); },
  })

  if (userType !== 'customer') return null
  if (isLoading) return <div className="text-center py-12">{t('common.loading')}</div>
  if (error || !data?.data) {
    return (
      <div className="text-center py-12">
        <p className="text-red-600">{t('orders.notFound')}</p>
        <Link to="/orders" className="mt-4 inline-block text-navy-700 hover:underline">
          {t('orders.backToList')}
        </Link>
      </div>
    )
  }

  const order = data.data as OrderPayload
  const canConfirmCod =
    order.status === 'awaiting_customer_confirmation'
    && order.payment_method !== 'paypal'
    && order.payment_method !== 'stripe'
  const canPayPal =
    order.status === 'awaiting_customer_confirmation' && order.payment_method === 'paypal'
  const canSimulatePayPal =
    canPayPal && settings.paypal_mode === 'sandbox'
  const canPayByCard =
    order.status === 'awaiting_customer_confirmation'
    && ['stripe', 'card'].includes((order.payment_method ?? '').toLowerCase())
  const actionError = confirmMut.error ?? paypalMut.error ?? paypalSimulationMut.error ?? stripeVerifyQuery.error
  const paypalErrorMessage = paypalResult && !['ok', 'cancelled'].includes(paypalResult)
    ? t(`orders.paypal.${paypalResult}`, 'PayPal could not complete payment. Review the order total and try again.')
    : null

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-6">
        <Link to="/orders" className="text-navy-700 hover:underline text-sm">
          ← {t('orders.backToList')}
        </Link>
      </div>
      <h1 className="font-mono font-bold text-[var(--color-text)] mb-6 break-all text-xl sm:text-2xl">
        {t('orders.order')} #{order._id}
      </h1>
      <div className="bg-white rounded-lg border border-slate-200 p-6 space-y-4">
        <div>
          <span className="text-sm font-medium text-slate-600">{t('admin.status')}:</span>{' '}
          <span className="text-slate-800">
            {t(`admin.orderStatus.${order.status}`, order.status)}
          </span>
        </div>
        {order.warehouse && (
          <>
            <div>
              <span className="text-sm font-medium text-slate-600">{t('admin.publisher', 'Publisher')}:</span>{' '}
              <span className="text-slate-800">
                {order.warehouse.publisher?.name ?? '-'}
              </span>
            </div>
            <div>
              <span className="text-sm font-medium text-slate-600">{t('admin.warehouse', 'Warehouse')}:</span>{' '}
              <span className="text-slate-800">
                {order.warehouse.name}
              </span>
            </div>
          </>
        )}
        <div className="space-y-1">
          {(order.books_subtotal != null || order.shipping_fee != null) && (
            <>
              <div className="text-sm text-slate-600 flex justify-between">
                <span>{t('orders.booksSubtotal', 'Books subtotal')}</span>
                <span>${(order.books_subtotal ?? 0).toFixed(2)}</span>
              </div>
              <div className="text-sm text-slate-600 flex justify-between">
                <span>{t('orders.shippingFee', 'Shipping fee')}</span>
                <span>${(order.shipping_fee ?? 0).toFixed(2)}</span>
              </div>
            </>
          )}
          <div>
            <span className="text-sm font-medium text-slate-600">{t('orders.total')}:</span>{' '}
            <span className="text-slate-800">${order.total?.toFixed(2)}</span>
          </div>
        </div>
        {order.shipping_method ? (
          <div>
            <span className="text-sm font-medium text-slate-600">
              {t('orders.shippingMethod', 'Shipping method')}:
            </span>{' '}
            <span className="text-slate-800">{order.shipping_method}</span>
          </div>
        ) : null}
        <div>
          <span className="text-sm font-medium text-slate-600">{t('checkout.paymentMethod')}:</span>{' '}
          <span className="text-slate-800">{paymentMethodLabel(order.payment_method, t)}</span>
        </div>
        <div>
          <span className="text-sm font-medium text-slate-600">{t('orders.paymentStatus')}:</span>{' '}
          <span className="text-slate-800">{paymentStatusLabel(order.payment_status, t)}</span>
        </div>
        {order.payment_info?.provider === 'paypal_sandbox_simulation' && (
          <p className="text-sm text-amber-900 bg-amber-50 border border-amber-300 rounded-lg p-3">
            {t('orders.paypalSimulationRecorded', 'Sandbox payment simulated for testing only. No money was charged and no PayPal transaction was created.')}
          </p>
        )}
        {order.status === 'awaiting_customer_confirmation' && (
          <>
            <p className="text-sm text-navy-900 bg-navy-50 border border-navy-200 rounded-lg p-3">
              {t(
                'orders.awaitingConfirmationHint',
                'The warehouse finalized this quote. Confirm to send it back for fulfillment, or pay with PayPal if that is your selected method.'
              )}
            </p>
            {stripeVerifyQuery.data?.success && (
              <p className="text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg p-3">
                {t('orders.cardPaymentSuccess', 'Card payment confirmed. Your order has been sent back to the warehouse.')}
              </p>
            )}
          </>
        )}
        {paypalResult === 'cancelled' && (
          <p className="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-3">
            {t('orders.paypal.cancelled', 'Payment was cancelled. Your order is still available below.')}
          </p>
        )}
        {paypalErrorMessage && (
          <p className="text-sm text-red-800 bg-red-50 border border-red-200 rounded-lg p-3">
            {paypalErrorMessage}
          </p>
        )}
        {order.shipping_address && (
          <div>
            <span className="text-sm font-medium text-slate-600">{t('checkout.shippingAddress')}:</span>
            <p className="mt-1 text-slate-700">
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
          <span className="text-sm font-medium text-slate-600">{t('admin.items')}:</span>
          <div className="mt-2 overflow-x-auto rounded-lg border border-slate-200">
            <table className="w-full table-fixed border-collapse text-sm">
              <thead>
                <tr className="bg-slate-100 border-b border-slate-200">
                  <th
                    scope="col"
                    className="min-w-0 w-[62%] px-3 py-2 font-medium text-slate-700 align-top text-start"
                  >
                    {t('orders.itemTitleCol')}
                  </th>
                  <th
                    scope="col"
                    className="w-[38%] px-3 py-2 font-medium text-slate-700 align-top text-end whitespace-nowrap"
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
                      className={`text-slate-800 align-top border-b border-slate-100 ${i % 2 === 1 ? 'bg-slate-50/80' : 'bg-white'}`}
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

        {(canConfirmCod || canPayPal || canPayByCard) && (
          <div className="pt-4 border-t border-slate-200 space-y-2">
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
                disabled={paypalMut.isPending || paypalSimulationMut.isPending}
                className="w-full py-2.5 rounded-lg font-medium text-white bg-[#00457C] hover:opacity-90 disabled:opacity-50"
                onClick={() => paypalMut.mutate()}
              >
                {paypalMut.isPending ? t('common.loading') : t('orders.payWithPayPal', 'Pay with PayPal')}
              </button>
            )}
            {canSimulatePayPal && (
              <button
                type="button"
                disabled={paypalMut.isPending || paypalSimulationMut.isPending}
                className="w-full py-2.5 rounded-lg font-medium text-amber-950 bg-amber-200 hover:bg-amber-300 disabled:opacity-50"
                onClick={() => { paypalSimulationMut.mutate(); }}
              >
                {paypalSimulationMut.isPending
                  ? t('common.loading')
                  : t('orders.simulatePayPalSandbox', 'Simulate PayPal payment (sandbox only)')}
              </button>
            )}
            {canPayByCard && (
              <button
                type="button"
                disabled={stripeMut.isPending}
                className="w-full py-2.5 rounded-lg font-medium text-white bg-[#635BFF] hover:opacity-90 disabled:opacity-50"
                onClick={() => { stripeMut.mutate(); }}
              >
                {stripeMut.isPending
                  ? t('common.loading')
                  : t('orders.payByCard', 'Pay by card')}
              </button>
            )}
          </div>
        )}

        {(actionError || stripeMut.error) && (
          <p className="text-red-600 text-sm">
            {((actionError ?? stripeMut.error) as { response?: { data?: { message?: string } } }).response?.data?.message ??
              ((actionError ?? stripeMut.error) as Error).message}
          </p>
        )}
      </div>
    </div>
  )
}
