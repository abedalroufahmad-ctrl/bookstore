import { useQuery } from '@tanstack/react-query'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { publishersPublic as publishersApi, books as booksApi } from '../lib/api'
import { getPublisherEntries, getWarehouseId } from '../lib/utils'
import { BookCard } from '../components/BookCard'
import { useSettings } from '../contexts/SettingsContext'
import { Pagination } from '../components/Pagination'
import { useAddToCart } from '../hooks/useAddToCart'
import type { Book, Publisher } from '../lib/api'

export function PublisherBooks() {
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

  const { data: publisherData, isLoading: publisherLoading } = useQuery({
    queryKey: ['publisher', id],
    queryFn: async () => {
      const res = await publishersApi.get(id!)
      return res.data
    },
    enabled: !!id,
  })

  const { data: booksData, isLoading: booksLoading } = useQuery({
    queryKey: ['books', 'publisher', id, page, search, settings.catalog_items_per_page],
    queryFn: async () => {
      const params: Record<string, string | number> = {
        publisher_id: id!,
        page,
        per_page: settings.catalog_items_per_page,
      }
      if (search) params.search = search
      const res = await booksApi.list(params)
      return res.data
    },
    enabled: !!id,
    placeholderData: (previousData) => previousData,
    staleTime: 60_000,
  })

  const publisher: Publisher | undefined = publisherData?.data
  const paginated = booksData?.data
  const bookItems: Book[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null
  const isLoading = publisherLoading || booksLoading

  if (isLoading) {
    return (
      <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
        {t('common.loading')}
      </div>
    )
  }

  return (
    <div>
      <div style={{ marginBottom: 24, fontSize: 14, color: '#78716c' }}>
        <Link to="/" style={{ color: '#92400e', textDecoration: 'none' }}>
          {t('nav.bookStore')}
        </Link>{' '}
        /{' '}
        <span>{t('publishers.title', 'Publishers')}</span>
        {' / '}
        {publisher?.name}
      </div>

      {publisher && (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 20,
            marginBottom: 32,
            padding: '24px 28px',
            background: '#fff',
            borderRadius: 12,
            border: '1px solid #e7e5e4',
          }}
        >
          <div
            style={{
              width: 72,
              height: 72,
              borderRadius: 16,
              background: '#fef3c7',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: 32,
              flexShrink: 0,
            }}
          >
            🏢
          </div>
          <div>
            <h1 style={{ fontSize: 24, fontWeight: 700, color: '#292524', margin: 0 }}>{publisher.name}</h1>
            <p style={{ fontSize: 13, color: '#a8a29e', marginTop: 8 }}>{meta?.total ?? bookItems.length}</p>
          </div>
        </div>
      )}

      {bookItems.length > 0 ? (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))',
            gap: 24,
          }}
        >
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
              onAddToCart={handleAddToCart}
              isAddingToCart={isAddingToCart(book._id)}
              isInCart={isInCart(book._id)}
            />
          ))}
        </div>
      ) : (
        <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
          <p style={{ fontSize: 16 }}>{t('publishers.noBooks', 'No books for this publisher')}</p>
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
