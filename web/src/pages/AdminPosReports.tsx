import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'

type ReportBucket = { period: string; total: number; count: number }

function formatDateTime(raw: unknown): string {
  if (raw == null || raw === '') return '-'
  const d = new Date(String(raw))
  return Number.isNaN(d.getTime()) ? String(raw) : d.toLocaleString()
}

export function AdminPosReports() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [warehouseFilter, setWarehouseFilter] = useState('')
  const [reportType, setReportType] = useState('daily')
  const [page, setPage] = useState(1)

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

  const { data: reportsData, isLoading: reportsLoading } = useQuery({
    queryKey: ['pos-reports', reportType, warehouseFilter],
    queryFn: async () => {
      const res = await admin.pos.reports({ type: reportType, warehouse_id: warehouseFilter || undefined })
      return res.data
    },
  })

  const { data: invoicesData, isLoading: invoicesLoading } = useQuery({
    queryKey: ['pos-invoices', page, warehouseFilter],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 15 }
      if (warehouseFilter) params.warehouse_id = warehouseFilter
      const res = await admin.pos.invoices(params)
      return res.data
    },
  })

  const payload = (reportsData as any)?.data ?? reportsData ?? {}
  const summary = payload.summary as {
    today?: ReportBucket
    month?: ReportBucket
    year?: ReportBucket
    all?: ReportBucket
  } | undefined
  const periods: ReportBucket[] = payload.periods ?? (Array.isArray(payload) ? payload : [])
  const invoicesBody = (invoicesData as any)?.data ?? invoicesData
  const invoicesList = Array.isArray(invoicesBody?.data) ? invoicesBody.data : (Array.isArray(invoicesBody) ? invoicesBody : [])
  const invoicesMeta = invoicesBody && typeof invoicesBody === 'object' && 'current_page' in invoicesBody ? invoicesBody : null

  const summaryCards = [
    { key: 'today', label: t('admin.totalToday'), data: summary?.today },
    { key: 'month', label: t('admin.totalThisMonth'), data: summary?.month },
    { key: 'year', label: t('admin.totalThisYear'), data: summary?.year },
    { key: 'all', label: t('admin.totalAllTime'), data: summary?.all },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
          <div className="flex flex-wrap gap-2 mb-3">
            <Link to="/admin/pos" className="px-4 py-2 rounded-lg text-sm font-medium bg-stone-100 text-stone-700 hover:bg-stone-200">
              {t('admin.posTerminal')}
            </Link>
            <span className="px-4 py-2 rounded-lg text-sm font-medium bg-amber-900 text-white">{t('admin.posReports')}</span>
          </div>
          <h1 className="text-2xl font-bold text-amber-900">{t('admin.posReports')}</h1>
        </div>

        <select
          value={warehouseFilter}
          onChange={(e) => {
            setWarehouseFilter(e.target.value)
            setPage(1)
          }}
          className="px-4 py-2 border border-stone-300 rounded-lg max-w-xs"
        >
          <option value="">{t('admin.allWarehouses')}</option>
          {warehouses.map((w: any) => (
            <option key={String(w._id)} value={String(w._id)}>{w.name}</option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {summaryCards.map((card) => (
          <div key={card.key} className="bg-white p-4 rounded-lg border border-stone-200 text-center">
            <div className="text-sm text-stone-500 mb-1">{card.label}</div>
            <div className="text-xl font-bold text-amber-900">${Number(card.data?.total ?? 0).toFixed(2)}</div>
            <div className="text-xs text-stone-400 mt-1">
              {t('admin.invoicesCount', { count: card.data?.count ?? 0 })}
            </div>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-lg border border-stone-200 p-6 print:hidden">
        <div className="flex gap-2 mb-6 border-b border-stone-200 pb-4">
          {['daily', 'monthly', 'yearly'].map((type) => (
            <button
              key={type}
              onClick={() => setReportType(type)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition ${reportType === type ? 'bg-amber-900 text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200'}`}
            >
              {t(`admin.report${type.charAt(0).toUpperCase() + type.slice(1)}`)}
            </button>
          ))}
        </div>

        {reportsLoading ? (
          <div className="text-center py-8">{t('common.loading')}</div>
        ) : periods.length === 0 ? (
          <div className="text-center py-8 text-stone-500">{t('admin.noReports')}</div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {periods.map((r) => (
              <div key={r.period} className="bg-stone-50 p-4 rounded-lg border border-stone-200 text-center">
                <div className="text-sm text-stone-500 font-mono mb-1">{r.period}</div>
                <div className="text-xl font-bold text-amber-900">${Number(r.total).toFixed(2)}</div>
                <div className="text-xs text-stone-400">{t('admin.invoicesCount', { count: r.count ?? 0 })}</div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden print:hidden">
        <div className="p-4 border-b border-stone-200 bg-stone-50">
          <h2 className="font-bold text-lg">{t('admin.recentInvoices')}</h2>
        </div>

        {invoicesLoading ? (
          <div className="text-center py-8">{t('common.loading')}</div>
        ) : invoicesList.length === 0 ? (
          <div className="text-center py-8 text-stone-500">{t('admin.noInvoices')}</div>
        ) : (
          <>
            <table className="w-full text-sm">
              <thead className="bg-stone-50 text-stone-700">
                <tr>
                  <th className="px-4 py-2 text-start">{t('admin.invoiceId')}</th>
                  <th className="px-4 py-2 text-start">{t('admin.date')}</th>
                  <th className="px-4 py-2 text-start">{t('admin.customer')}</th>
                  <th className="px-4 py-2 text-start">{t('admin.warehouse')}</th>
                  <th className="px-4 py-2 text-end">{t('admin.total')}</th>
                  <th className="px-4 py-2 text-end print:hidden">{t('admin.openInvoice')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-200">
                {invoicesList.map((inv: any, i: number) => {
                  const invoiceId = String(inv._id ?? inv.id ?? '')
                  return (
                  <tr
                    key={invoiceId || `inv-${i}`}
                    className="hover:bg-amber-50/70 cursor-pointer"
                    onClick={() => {
                      if (invoiceId) navigate(`/admin/pos/invoices/${invoiceId}`)
                    }}
                  >
                    <td className="px-4 py-2 font-mono text-xs text-stone-500">{invoiceId}</td>
                    <td className="px-4 py-2">{inv.created_at ? formatDateTime(inv.created_at) : '-'}</td>
                    <td className="px-4 py-2">{inv.customer_name || t('admin.walkInCustomer')}</td>
                    <td className="px-4 py-2">{warehouses.find((w: any) => String(w._id) === String(inv.warehouse_id))?.name || inv.warehouse_id}</td>
                    <td className="px-4 py-2 text-end font-medium">${Number(inv.total ?? 0).toFixed(2)}</td>
                    <td className="px-4 py-2 text-end print:hidden">
                      <Link
                        to={`/admin/pos/invoices/${invoiceId}`}
                        className="inline-block px-3 py-1 rounded bg-amber-900 text-white text-xs font-medium hover:bg-amber-800"
                        onClick={(e) => e.stopPropagation()}
                      >
                        {t('admin.openInvoice')}
                      </Link>
                    </td>
                  </tr>
                  )
                })}
              </tbody>
            </table>

            {invoicesMeta && (
              <div className="p-4 border-t border-stone-200 flex justify-center">
                <Pagination
                  currentPage={Number(invoicesMeta.current_page) || 1}
                  lastPage={Number(invoicesMeta.last_page) || 1}
                  total={Number(invoicesMeta.total) || 0}
                  perPage={Number(invoicesMeta.per_page) || 15}
                  onPageChange={setPage}
                />
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
