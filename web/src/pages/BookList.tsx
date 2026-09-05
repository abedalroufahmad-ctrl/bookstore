import { useEffect, useState } from 'react'
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
import { type BookConditionFilterValue } from '../components/BookConditionFilter'
import { Pagination } from '../components/Pagination'
import { useSettings } from '../contexts/SettingsContext'
import { useAddToCart } from '../hooks/useAddToCart'
import { getPublisherEntries, getWarehouseId } from '../lib/utils'

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

function categoryLabel(c: Category, lang?: string) {
  const title =
    lang?.startsWith('ar') && c.subject_title_ar
      ? c.subject_title_ar
      : c.subject_title_en
  return title || c.dewey_code
}

function bookCategoryName(book: {
  category?: { subject_title_en?: string; subject_title_ar?: string; dewey_code?: string } | null
}, lang?: string): string | null {
  const c = book.category
  if (!c) return null
  if (lang?.startsWith('ar') && c.subject_title_ar) return c.subject_title_ar
  return c.subject_title_en || c.dewey_code || null
}

type DraftFilters = {
  condition: BookConditionFilterValue
  categoryId: string
  warehouseId: string
  publisherId: string
}

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

  const [draft, setDraft] = useState<DraftFilters>({
    condition,
    categoryId,
    warehouseId,
    publisherId,
  })

  useEffect(() => {
    setDraft({ condition, categoryId, warehouseId, publisherId })
  }, [condition, categoryId, warehouseId, publisherId])

  const applyFilters = (next: DraftFilters) => {
    const params = new URLSearchParams(searchParams)
    const setOrDelete = (key: string, value: string) => {
      if (value) params.set(key, value)
      else params.delete(key)
    }
    setOrDelete('condition', next.condition)
    setOrDelete('category_id', next.categoryId)
    setOrDelete('warehouse_id', next.warehouseId)
    setOrDelete('publisher_id', next.publisherId)
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

  const toggleCategory = (id: string) => {
    setDraft((prev) => ({
      ...prev,
      categoryId: prev.categoryId === id ? '' : id,
    }))
  }

  return (
    <div className="shop-layout">
      <aside className="shop-sidebar">
        <h2 className="shop-sidebar-title">{t('books.filterOptions')}</h2>

        <div className="shop-filter-section">
          <h3 className="shop-filter-heading">{t('books.shopByCategory')}</h3>
          <div className="shop-filter-list">
            {categoryList.map((c) => (
              <label key={c._id} className="shop-filter-item">
                <input
                  type="checkbox"
                  checked={draft.categoryId === c._id}
                  onChange={() => toggleCategory(c._id)}
                />
                <span>{categoryLabel(c, i18n.language)}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="shop-filter-section">
          <h3 className="shop-filter-heading">{t('books.choosePublisher')}</h3>
          <div className="shop-filter-list single-col">
            {publisherList.map((p) => (
              <label key={p._id} className="shop-filter-item">
                <input
                  type="radio"
                  name="shop-publisher"
                  checked={draft.publisherId === p._id}
                  onChange={() => setDraft((prev) => ({ ...prev, publisherId: p._id }))}
                />
                <span>{p.name}</span>
              </label>
            ))}
            <label className="shop-filter-item">
              <input
                type="radio"
                name="shop-publisher"
                checked={draft.publisherId === ''}
                onChange={() => setDraft((prev) => ({ ...prev, publisherId: '' }))}
              />
              <span>{t('books.filterAll')}</span>
            </label>
          </div>
        </div>

        <div className="shop-filter-section">
          <h3 className="shop-filter-heading">{t('bookDetail.warehouse')}</h3>
          <div className="shop-filter-list single-col">
            {warehouseList.map((w) => (
              <label key={w._id} className="shop-filter-item">
                <input
                  type="radio"
                  name="shop-warehouse"
                  checked={draft.warehouseId === w._id}
                  onChange={() => setDraft((prev) => ({ ...prev, warehouseId: w._id }))}
                />
                <span>{w.name}</span>
              </label>
            ))}
            <label className="shop-filter-item">
              <input
                type="radio"
                name="shop-warehouse"
                checked={draft.warehouseId === ''}
                onChange={() => setDraft((prev) => ({ ...prev, warehouseId: '' }))}
              />
              <span>{t('books.filterAll')}</span>
            </label>
          </div>
        </div>

        <div className="shop-filter-section">
          <h3 className="shop-filter-heading">{t('books.conditionFilter')}</h3>
          <div className="shop-filter-list single-col">
            {([
              { value: '' as const, label: t('books.filterAll') },
              { value: 'new' as const, label: t('bookDetail.conditionNew') },
              { value: 'used' as const, label: t('bookDetail.conditionUsed') },
            ]).map((opt) => (
              <label key={opt.value || 'all'} className="shop-filter-item">
                <input
                  type="radio"
                  name="shop-condition"
                  checked={draft.condition === opt.value}
                  onChange={() => setDraft((prev) => ({ ...prev, condition: opt.value }))}
                />
                <span>{opt.label}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="shop-filter-actions">
          <button type="button" className="shop-btn-primary" onClick={() => applyFilters(draft)}>
            {t('books.refineSearch')}
          </button>
          <button
            type="button"
            className="shop-btn-outline"
            onClick={() => {
              const cleared: DraftFilters = {
                condition: '',
                categoryId: '',
                warehouseId: '',
                publisherId: '',
              }
              setDraft(cleared)
              applyFilters(cleared)
            }}
            disabled={!hasActiveFilters && !draft.categoryId && !draft.publisherId && !draft.warehouseId && !draft.condition}
          >
            {t('books.resetFilter')}
          </button>
        </div>
      </aside>

      <div>
        <div className="shop-toolbar">
          <h1 className="shop-toolbar-title">{t('books.title')}</h1>
          {hasActiveFilters && (
            <button
              type="button"
              className="text-sm font-medium hover:underline"
              style={{ color: 'var(--color-accent)' }}
              onClick={() => {
                const cleared: DraftFilters = {
                  condition: '',
                  categoryId: '',
                  warehouseId: '',
                  publisherId: '',
                }
                setDraft(cleared)
                applyFilters(cleared)
              }}
            >
              {t('books.clearFilters')}
            </button>
          )}
        </div>

        <div className="books-grid">
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
              publishers={getPublisherEntries(book)}
              warehouseName={book.warehouse?.name}
              warehouseId={getWarehouseId(book)}
              categoryName={bookCategoryName(book, i18n.language)}
              weight={book.weight}
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
    </div>
  )
}
