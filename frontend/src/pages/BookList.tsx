import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { books, cart } from '../lib/api'
import { BookCard } from '../components/BookCard'
import { Pagination } from '../components/Pagination'
import { useSettings } from '../contexts/SettingsContext'
import { useAuth } from '../contexts/AuthContext'

export function BookList() {
  const { t } = useTranslation()
  const { settings } = useSettings()
  const { userType } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()

  const addToCartMutation = useMutation({
    mutationFn: (bookId: string) => cart.addItem(bookId, 1),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  const handleAddToCart = (bookId: string) => {
    if (userType !== 'customer') {
      navigate('/login')
      return
    }
    addToCartMutation.mutate(bookId, {
      onError: (err: { response?: { status?: number; data?: { message?: string } } }) => {
        if (err.response?.status === 401) {
          navigate('/login')
          return
        }
        const message = err.response?.data?.message ?? t('common.error')
        alert(message)
      },
    })
  }
  const search = searchParams.get('search') ?? ''
  const page = parseInt(searchParams.get('page') ?? '1', 10)
  const setPage = (p: number) => {
    const params = new URLSearchParams(searchParams)
    params.set('page', String(p))
    setSearchParams(params)
  }
  const { data, isLoading, error } = useQuery({
    queryKey: ['books', page, search, settings.catalog_items_per_page],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: settings.catalog_items_per_page }
      if (search) params.search = search
      const res = await books.list(params)
      return res.data
    },
  })

  const { data: cartData } = useQuery({
    queryKey: ['cart'],
    queryFn: async () => {
      const res = await cart.get()
      return res.data
    },
    enabled: userType === 'customer',
  })

  const cartBookIds = (cartData?.data?.items ?? []).map((item: { book_id: string }) => item.book_id)

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
            onAddToCart={handleAddToCart}
            isAddingToCart={addToCartMutation.isPending && addToCartMutation.variables === book._id}
            isInCart={cartBookIds.includes(book._id)}
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
