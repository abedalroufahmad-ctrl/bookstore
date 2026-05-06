import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { warehousesPublic as warehousesApi } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { useSettings } from '../contexts/SettingsContext'
import type { Warehouse } from '../lib/api'

export function WarehouseList() {
  const { t } = useTranslation()
  const { settings } = useSettings()
  const [searchParams, setSearchParams] = useSearchParams()
  const search = searchParams.get('search') ?? ''
  const page = parseInt(searchParams.get('page') ?? '1', 10)
  const setPage = (p: number) => {
    const params = new URLSearchParams(searchParams)
    params.set('page', String(p))
    setSearchParams(params)
  }

  const { data, isLoading, error } = useQuery({
    queryKey: ['warehouses', page, search, settings.catalog_items_per_page],
    queryFn: async () => {
      const queryParams: Record<string, string | number> = {
        page,
        per_page: settings.catalog_items_per_page,
      }
      if (search) queryParams.search = search
      const res = await warehousesApi.list(queryParams)
      return res.data
    },
  })

  const paginated = data?.data
  const items: Warehouse[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  if (isLoading) {
    return (
      <div className="text-center py-20" style={{ color: 'var(--color-text-muted)' }}>
        <div
          className="mx-auto mb-4 rounded-full border-2 animate-spin"
          style={{
            width: 44,
            height: 44,
            borderColor: 'var(--color-border)',
            borderTopColor: 'var(--color-primary)',
          }}
        />
        {t('common.loading')}
      </div>
    )
  }

  if (error) {
    return (
      <div className="text-center py-20" style={{ color: 'var(--color-discount)' }}>
        {t('warehouses.loadError')}
      </div>
    )
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text)' }}>
          {t('warehouses.title')}
        </h1>
      </div>

      <div
        className="grid gap-4"
        style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))' }}
      >
        {items.map((w) => (
          <Link
            key={w._id}
            to={`/warehouses/${w._id}`}
            className="category-card flex items-center gap-4"
            style={{ textDecoration: 'none', color: 'inherit' }}
          >
            <div
              style={{
                width: 56,
                height: 56,
                borderRadius: 12,
                background: 'var(--color-primary-light)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: 26,
                flexShrink: 0,
              }}
            >
              🏭
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 16, fontWeight: 600, color: 'var(--color-text)' }}>{w.name}</div>
              <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginTop: 4 }}>
                {[w.city, w.country].filter(Boolean).join(', ')}
              </div>
            </div>
            <div style={{ fontSize: 18, color: 'var(--color-primary)', flexShrink: 0 }}>←</div>
          </Link>
        ))}
      </div>

      {items.length === 0 && (
        <div className="text-center py-20" style={{ color: 'var(--color-text-muted)' }}>
          <div className="text-5xl mb-4">🏭</div>
          <p className="text-lg">{t('warehouses.noWarehouses')}</p>
        </div>
      )}
      {meta && (
        <div style={{ marginTop: 24 }}>
          <Pagination
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            perPage={meta.per_page}
            onPageChange={setPage}
          />
        </div>
      )}
    </div>
  )
}
