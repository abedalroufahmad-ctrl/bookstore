import { useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  books,
  categories as categoriesApi,
  warehousesPublic,
  publishersPublic,
  type Category,
  type Warehouse,
  type Publisher,
} from '../lib/api'
import { BookCard } from '../components/BookCard'
import { BookConditionFilter, type BookConditionFilterValue } from '../components/BookConditionFilter'
import { Pagination } from '../components/Pagination'
import { useSettings } from '../contexts/SettingsContext'
import { useAddToCart } from '../hooks/useAddToCart'
import { getPublisherLabel, getPublisherId, getWarehouseId } from '../lib/utils'

function parseCondition(raw: string | null): BookConditionFilterValue {
  if (raw === 'new' || raw === 'used') return raw
  return ''
}

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in (d.data as object)) {
    return ((d.data as { data: T[] }).data) ?? []
  }
  return Array.isArray(d) ? (d as T[]) : []
}

const selectClass =
  'px-3 py-2 text-sm rounded-lg border min-w-[10rem] max-w-full focus:ring-2 focus:outline-none'

export function BookList() {
  const { t, i18n } = useTranslation()
  const { settings } = useSettings()
  const [searchParams, setSearchParams] = useSearchParams()
  const { handleAddToCart, isAddingToCart, isInCart } = useAddToCart()

  const search = searchParams.get('search') ?? ''
  const condition = parseCondition(searchParams.get('condition'))
  const categoryId = searchParams.get('category_id') ?? ''
  const warehouseId = searchParams.get('warehouse_id') ?? ''
  const publisherId = searchParams.get('publisher_id') ?? ''
  const page = parseInt(searchParams.get('page') ?? '1', 10)

  const patchParams = (patch: Record<string, string | null>) => {
    const params = new URLSearchParams(searchParams)
    for (const [key, value] of Object.entries(patch)) {
      if (value) params.set(key, value)
      else params.delete(key)
    }
    params.set('page', '1')
    setSearchParams(params)
  }

  const setPage = (p: number) => {
    const params = new URLSearchParams(searchParams)
    params.set('page', String(p))
    setSearchParams(params)
  }

  const { data: categoriesData } = useQuery({
    queryKey: ['categories-filter'],
    queryFn: async () => {
      const res = await categoriesApi.list({ per_page: 200 })
      return res.data
    },
    staleTime: 300_000,
  })

  const { data: warehousesData } = useQuery({
    queryKey: ['warehouses-filter'],
    queryFn: async () => {
      const res = await warehousesPublic.list({ per_page: 200 })
      return res.data
    },
    staleTime: 300_000,
  })

  const { data: publishersData } = useQuery({
    queryKey: ['publishers-filter'],
    queryFn: async () => {
      const res = await publishersPublic.list({ per_page: 200 })
      return res.data
    },
    staleTime: 300_000,
  })

  const categoryList = extractList<Category>(categoriesData)
  const warehouseList = extractList<Warehouse>(warehousesData)
  const publisherList = extractList<Publisher>(publishersData)

  const { data, isLoading, error } = useQuery({
    queryKey: [
      'books',
      page,
      search,
      condition,
      categoryId,
      warehouseId,
      publisherId,
      settings.catalog_items_per_page,
    ],
    queryFn: async () => {
      const params: Record<string, string | number> = {
        page,
        per_page: settings.catalog_items_per_page,
      }
      if (search) params.search = search
      if (condition) params.condition = condition
      if (categoryId) params.category_id = categoryId
      if (warehouseId) params.warehouse_id = warehouseId
      if (publisherId) params.publisher_id = publisherId
      const res = await books.list(params)
      return res.data
    },
    placeholderData: (previousData) => previousData,
    staleTime: 60_000,
  })

  if (isLoading && !data) {
    return (
      <div className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
        {t('common.loading')}
      </div>
    )
  }
  if (error) {
    return (
      <div className="text-center py-12" style={{ color: 'var(--color-discount)' }}>
        {t('books.failedToLoad')}
      </div>
    )
  }

  const paginated = data?.data
  const items = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null
  const hasActiveFilters = Boolean(condition || categoryId || warehouseId || publisherId)

  const categoryLabel = (c: Category) => {
    const title =
      i18n.language?.startsWith('ar') && c.subject_title_ar
        ? c.subject_title_ar
        : c.subject_title_en
    return `${title} (${c.dewey_code})`
  }

  return (
    <div>
      <div className="flex flex-col gap-4 mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text)' }}>
            {t('books.title')}
          </h1>
          <BookConditionFilter
            value={condition}
            onChange={(next) => patchParams({ condition: next || null })}
          />
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <label className="flex flex-col gap-1 text-sm">
            <span style={{ color: 'var(--color-text-muted)' }}>{t('bookDetail.category')}</span>
            <select
              value={categoryId}
              onChange={(e) => patchParams({ category_id: e.target.value || null })}
              className={selectClass}
              style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }}
            >
              <option value="">{t('books.filterAll')}</option>
              {categoryList.map((c) => (
                <option key={c._id} value={c._id}>
                  {categoryLabel(c)}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span style={{ color: 'var(--color-text-muted)' }}>{t('bookDetail.publisher')}</span>
            <select
              value={publisherId}
              onChange={(e) => patchParams({ publisher_id: e.target.value || null })}
              className={selectClass}
              style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }}
            >
              <option value="">{t('books.filterAll')}</option>
              {publisherList.map((p) => (
                <option key={p._id} value={p._id}>
                  {p.name}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span style={{ color: 'var(--color-text-muted)' }}>{t('bookDetail.warehouse')}</span>
            <select
              value={warehouseId}
              onChange={(e) => patchParams({ warehouse_id: e.target.value || null })}
              className={selectClass}
              style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }}
            >
              <option value="">{t('books.filterAll')}</option>
              {warehouseList.map((w) => (
                <option key={w._id} value={w._id}>
                  {w.name}
                </option>
              ))}
            </select>
          </label>

          {hasActiveFilters && (
            <button
              type="button"
              onClick={() =>
                patchParams({
                  condition: null,
                  category_id: null,
                  publisher_id: null,
                  warehouse_id: null,
                })
              }
              className="px-3 py-2 text-sm rounded-lg border hover:opacity-80"
              style={{ borderColor: 'var(--color-border)', color: 'var(--color-primary)' }}
            >
              {t('books.clearFilters')}
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
        {items.map((book: any) => (
          <BookCard
            key={book._id}
            id={book._id}
            title={book.title}
            price={book.price}
            coverImage={book.cover_image}
            coverImageThumb={book.cover_image_thumb}
            authorName={book.authors?.map((a: any) => a.name).join('، ') || ''}
            authors={book.authors}
            publisher={getPublisherLabel(book)}
            publisherId={getPublisherId(book)}
            warehouseName={book.warehouse?.name}
            warehouseId={getWarehouseId(book)}
            discountPercent={book.discount_percent}
            globalDiscount={settings.global_discount}
            condition={book.condition}
            isSold={book.is_sold}
            onAddToCart={book.is_sold ? undefined : handleAddToCart}
            isAddingToCart={isAddingToCart(book._id)}
            isInCart={isInCart(book._id)}
          />
        ))}
      </div>
      {items.length === 0 && (
        <p className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>
          {t('books.noBooks')}
        </p>
      )}
      {meta && (
        <Pagination
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          total={meta.total}
          perPage={meta.per_page}
          onPageChange={setPage}
          maxNavigablePage={
            typeof (meta as { max_page?: number }).max_page === 'number'
              ? (meta as { max_page: number }).max_page
              : 200
          }
        />
      )}
    </div>
  )
}
