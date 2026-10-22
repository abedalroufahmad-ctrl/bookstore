import { useQuery } from '@tanstack/react-query'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { warehousesPublic as warehousesApi, books as booksApi } from '../lib/api'
import { getPublisherEntries, getWarehouseId } from '../lib/utils'
import { BookCard } from '../components/BookCard'
import { useSettings } from '../contexts/settingsContextCore'
import { catalogGridStyle } from '../contexts/settingsUtils'
import { Pagination } from '../components/Pagination'
import { useAddToCart } from '../hooks/useAddToCart'
import type { Book, Warehouse } from '../lib/api'

export function WarehouseBooks() {
  const { id } = useParams<{ id: string }>()
  const [searchParams, setSearchParams] = useSearchParams()
  const { handleAddToCart, isAddingToCart, isInCart } = useAddToCart()

  const search = searchParams.get('search') ?? ''
  const page = parseInt(searchParams.get('page') ?? '1', 10)
  const setPage = (p: number) => {
    const params = new URLSearchParams(searchParams)
    params.set('page', String(p))
    setSearchParams(params)
  }

  const { t } = useTranslation()
  const { settings } = useSettings()
  const perPage = settings.books.per_page

  const { data: warehouseData, isLoading: warehouseLoading } = useQuery({
    queryKey: ['warehouse', id],
    queryFn: async () => {
      const res = await warehousesApi.get(id!)
      return res.data
    },
    enabled: !!id,
  })

  const { data: booksData, isLoading: booksLoading } = useQuery({
    queryKey: ['books', 'warehouse', id, page, search, perPage],
    queryFn: async () => {
      const params: Record<string, string | number> = {
        warehouse_id: id!,
        page,
        per_page: perPage,
      }
      if (search) params.search = search
      const res = await booksApi.list(params)
      return res.data
    },
    enabled: !!id,
  })

  const warehouse: Warehouse | undefined = warehouseData?.data
  const paginated = booksData?.data
  const bookItems: Book[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null
  const isLoading = warehouseLoading || booksLoading

  if (isLoading) {
    return (
      <div style={{ textAlign: 'center', padding: '60px 0', color: '#64748b' }}>
        <div
          style={{
            width: 40,
            height: 40,
            border: '3px solid #e2e8f0',
            borderTopColor: '#1f2f58',
            borderRadius: '50%',
            animation: 'spin 0.8s linear infinite',
            margin: '0 auto 16px',
          }}
        />
        <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
        {t('common.loading')}
      </div>
    )
  }

  return (
    <div>
      <div style={{ marginBottom: 24, fontSize: 14, color: '#64748b' }}>
        <Link to="/" style={{ color: '#1f2f58', textDecoration: 'none' }}>
          {t('nav.bookStore')}
        </Link>{' '}
        /{' '}
        <Link to="/warehouses" style={{ color: '#1f2f58', textDecoration: 'none' }}>
          {t('warehouses.title')}
        </Link>{' '}
        / {warehouse?.name}
      </div>

      {warehouse && (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 20,
            marginBottom: 32,
            padding: '24px 28px',
            background: '#fff',
            borderRadius: 12,
            border: '1px solid #e2e8f0',
          }}
        >
          <div
            style={{
              width: 72,
              height: 72,
              borderRadius: 16,
              background: '#e3e9f5',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: 32,
              flexShrink: 0,
            }}
          >
            🏭
          </div>
          <div>
            <h1 style={{ fontSize: 24, fontWeight: 700, color: '#1e293b', margin: 0 }}>{warehouse.name}</h1>
            <p style={{ fontSize: 13, color: '#64748b', marginTop: 8 }}>
              {[warehouse.city, warehouse.country].filter(Boolean).join(' · ')}
            </p>
            <p style={{ fontSize: 13, color: '#94a3b8', marginTop: 8 }}>{meta?.total ?? bookItems.length}</p>
          </div>
        </div>
      )}

      {bookItems.length > 0 ? (
        <div className="books-grid" style={catalogGridStyle(settings.books.per_row)}>
          {bookItems.map((book) => (
            <BookCard
              key={book._id}
              id={book._id}
              title={book.title}
              price={book.price}
              coverImage={book.cover_image}
              coverImageThumb={book.cover_image_thumb}
              authorName={book.authors?.map((a) => a.name).join('، ')}
              authors={book.authors}
              publishers={getPublisherEntries(book)}
              warehouseName={book.warehouse?.name}
              warehouseId={getWarehouseId(book)}
              weight={book.weight}
                            categoryName={(book.category && (book.category.subject_title_en || book.category.subject_title_ar || book.category.dewey_code)) || undefined}
              discountPercent={book.discount_percent ?? 0}
              globalDiscount={settings.global_discount ?? 0}
              condition={book.condition}
              isSold={book.is_sold}
              onAddToCart={handleAddToCart}
              isAddingToCart={isAddingToCart(book._id)}
              isInCart={isInCart(book._id)}
            />
          ))}
        </div>
      ) : (
        <div style={{ textAlign: 'center', padding: '60px 0', color: '#64748b' }}>
          <div style={{ fontSize: 48, marginBottom: 16 }}>📚</div>
          <p style={{ fontSize: 16 }}>{t('warehouses.noBooks')}</p>
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
            maxNavigablePage={typeof (meta as { max_page?: number }).max_page === 'number'
              ? (meta as { max_page: number }).max_page
              : 200}
          />
        </div>
      )}
    </div>
  )
}
