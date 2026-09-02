import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'

function unwrapInvoice(payload: unknown): any {
  if (!payload || typeof payload !== 'object') return null
  const root = payload as Record<string, unknown>
  const nested = root.data
  if (nested && typeof nested === 'object' && ('_id' in (nested as object) || 'items' in (nested as object))) {
    return nested
  }
  if ('_id' in root || 'items' in root) return root
  return nested ?? null
}

function formatDateTime(raw: unknown): string {
  if (raw == null || raw === '') return '-'
  const d = new Date(String(raw))
  return Number.isNaN(d.getTime()) ? String(raw) : d.toLocaleString()
}

export function AdminPosInvoice() {
  const { id } = useParams<{ id: string }>()
  const { t } = useTranslation()

  const { data: warehousesData } = useQuery({
    queryKey: ['admin-warehouses'],
    queryFn: async () => {
      const res = await admin.warehouses.list({ per_page: 100 })
      return res.data
    },
  })
  const warehousesRaw = (warehousesData as any)?.data ?? warehousesData
  const warehouses = Array.isArray(warehousesRaw)
    ? warehousesRaw
    : Array.isArray(warehousesRaw?.data)
      ? warehousesRaw.data
      : []

  const { data, isLoading, error } = useQuery({
    queryKey: ['pos-invoice', id],
    queryFn: async () => {
      const res = await admin.pos.getInvoice(id!)
      return unwrapInvoice(res.data)
    },
    enabled: Boolean(id),
  })

  if (isLoading) {
    return <div className="text-center py-12">{t('common.loading')}</div>
  }

  if (error || !data) {
    return (
      <div className="text-center py-12">
        <p className="text-red-600">{t('admin.invoiceNotFound')}</p>
        <Link to="/admin/pos/reports" className="mt-4 inline-block text-amber-800 hover:underline">
          {t('admin.backToInvoices')}
        </Link>
      </div>
    )
  }

  const warehouseName =
    data.warehouse?.name
    ?? warehouses.find((w: { _id: string }) => String(w._id) === String(data.warehouse_id))?.name
    ?? data.warehouse_id
  const items = Array.isArray(data.items) ? data.items : []

  return (
    <div className="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-stone-200">
      <div className="text-center mb-6">
        <h1 className="text-2xl font-bold mb-2">{t('admin.invoiceId')}</h1>
        <p className="text-stone-600 font-mono text-sm">#{data._id}</p>
      </div>

      <div className="mb-6 space-y-2 text-sm border-b border-stone-200 pb-6">
        <p><strong>{t('admin.date')}:</strong> {formatDateTime(data.created_at)}</p>
        <p><strong>{t('admin.warehouse')}:</strong> {warehouseName || '-'}</p>
        <p>
          <strong>{t('admin.customer')}:</strong>{' '}
          {data.customer_name || t('admin.walkInCustomer')}
        </p>
      </div>

      <table className="w-full text-sm mb-6">
        <thead>
          <tr className="border-b border-stone-200">
            <th className="text-start py-2">{t('orders.itemTitleCol')}</th>
            <th className="text-end py-2">{t('orders.itemPriceCol')}</th>
          </tr>
        </thead>
        <tbody>
          {items.map((item: { book_title?: string; book_id?: string; quantity?: number; price?: number }, i: number) => (
            <tr key={i} className="border-b border-stone-100">
              <td className="py-2">
                {item.book_title || item.book_id} <span className="text-stone-500">x{item.quantity}</span>
              </td>
              <td className="py-2 text-end">${(Number(item.price) * Number(item.quantity ?? 0)).toFixed(2)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <th className="text-start py-4 text-lg">{t('orders.total')}</th>
            <th className="text-end py-4 text-lg">${Number(data.total ?? 0).toFixed(2)}</th>
          </tr>
          {data.publisher_payout_amount != null && (
            <>
              <tr>
                <td className="text-start py-1 text-sm text-stone-600">{t('admin.publisherPayoutAmount')}</td>
                <td className="text-end py-1 text-sm text-stone-600">${Number(data.publisher_payout_amount).toFixed(2)}</td>
              </tr>
              <tr>
                <td className="text-start py-1 text-sm text-stone-600">
                  {t('admin.platformCommissionAmount')}
                  {data.platform_commission_percent != null ? ` (${Number(data.platform_commission_percent)}%)` : ''}
                </td>
                <td className="text-end py-1 text-sm text-stone-600">${Number(data.platform_commission_amount ?? 0).toFixed(2)}</td>
              </tr>
            </>
          )}
        </tfoot>
      </table>

      <div className="flex gap-4 print:hidden">
        <button onClick={() => window.print()} className="flex-1 bg-stone-800 text-white py-2 rounded">
          {t('admin.print')}
        </button>
        <Link to="/admin/pos/reports" className="flex-1 border border-stone-300 py-2 rounded text-center">
          {t('admin.backToInvoices')}
        </Link>
      </div>
    </div>
  )
}
