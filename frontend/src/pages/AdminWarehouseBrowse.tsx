import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import type { Warehouse } from '../lib/api'

export function AdminWarehouseBrowse() {
  const { t } = useTranslation()

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-warehouses-browse'],
    queryFn: async () => {
      const res = await admin.warehouses.list({ per_page: 100 })
      return res.data
    },
  })

  const paginated = data?.data
  const items: Warehouse[] =
    paginated && 'data' in paginated ? ((paginated as { data: Warehouse[] }).data ?? []) : []

  if (isLoading) {
    return <div className="text-center py-12 text-stone-500">{t('common.loading')}</div>
  }

  if (error) {
    return (
      <div className="text-center py-12 text-red-600">
        {(error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
          t('common.error')}
      </div>
    )
  }

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-2">{t('admin.warehouseBooksTitle')}</h1>
      <p className="text-stone-600 mb-6">{t('admin.warehouseBooksHint')}</p>
      <div
        className="grid gap-4"
        style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))' }}
      >
        {items.map((w) => (
          <Link
            key={w._id}
            to={`/admin/warehouse-books/${w._id}`}
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
            style={{ textDecoration: 'none', color: 'inherit' }}
          >
            <div className="text-2xl mb-2">🏭</div>
            <h2 className="font-semibold text-amber-900">{w.name}</h2>
            <p className="text-sm text-stone-500 mt-1">{[w.city, w.country].filter(Boolean).join(', ')}</p>
          </Link>
        ))}
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-12">{t('admin.noWarehouses')}</p>
      )}
    </div>
  )
}
