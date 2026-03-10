import { useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { books } from '../lib/api'
import { BookCard } from '../components/BookCard'
import { Pagination } from '../components/Pagination'
import { useSettings } from '../contexts/SettingsContext'

export function BookList() {
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
    queryKey: ['books', page, search],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 32 }
      if (search) params.search = search
      const res = await books.list(params)
      return res.data
    },
  })

  if (isLoading) return <div className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>{t('common.loading')}</div>
  if (error) return <div className="text-center py-12" style={{ color: 'var(--color-discount)' }}>{t('books.failedToLoad')}</div>

  const paginated = data?.data
  const items = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  // The resolveCoverUrl function is no longer needed here as BookCard will handle image resolution
  // const apiBase = import.meta.env.VITE_API_URL || '/api/v1'
  // const resolveCoverUrl = (path: string) =>
  //   path.startsWith('http://') || path.startsWith('https://')
  //     ? path
  //     : `${apiBase.replace(/\/api\/v1$/, '')}${path.startsWith('/') ? path : `/${path}`}`

  return (
    <div>
      <h1 className="text-2xl font-bold mb-6" style={{ color: 'var(--color-text)' }}>
        {t('books.title')}
      </h1>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
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
            discountPercent={book.discount_percent}
            globalDiscount={settings.global_discount}
          />
        ))}
      </div>
      {items.length === 0 && (
        <p className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>{t('books.noBooks')}</p>
      )}
      {meta && (
        <Pagination
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          total={meta.total}
          perPage={meta.per_page}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
