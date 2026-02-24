import { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useParams, useNavigate } from 'react-router-dom'
import { books, cart } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

export function BookDetail() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { userType } = useAuth()
  const [showOriginal, setShowOriginal] = useState(false)

  useEffect(() => {
    const onEsc = (e: KeyboardEvent) => e.key === 'Escape' && setShowOriginal(false)
    if (showOriginal) {
      document.addEventListener('keydown', onEsc)
      document.body.style.overflow = 'hidden'
    }
    return () => {
      document.removeEventListener('keydown', onEsc)
      document.body.style.overflow = ''
    }
  }, [showOriginal])

  const { data, isLoading, error } = useQuery({
    queryKey: ['book', id],
    queryFn: async () => {
      const res = await books.get(id!)
      return res.data
    },
    enabled: !!id,
  })

  const addToCart = async () => {
    if (userType !== 'customer') {
      navigate('/login')
      return
    }
    try {
      await cart.addItem(id!, 1)
      navigate('/cart')
    } catch {
      alert('Failed to add to cart')
    }
  }

  if (isLoading) return <div className="text-center py-12">Loading...</div>
  if (error || !data?.data) return <div className="text-center py-12 text-red-600">Book not found</div>

  const book = data.data.data ?? data.data
  const authors = Array.isArray(book.authors) ? book.authors : []
  const category = book.category

  const apiBase = import.meta.env.VITE_API_URL || '/api/v1'
  const resolveCoverUrl = (path: string) =>
    path.startsWith('http://') || path.startsWith('https://')
      ? path
      : `${apiBase.replace(/\/api\/v1$/, '')}${path.startsWith('/') ? path : `/${path}`}`

  const InfoRow = ({ label, value }: { label: string; value: React.ReactNode }) =>
    value != null && value !== '' ? (
      <div className="flex gap-2 py-1">
        <span className="font-medium text-stone-500 w-24">{label}:</span>
        <span>{value}</span>
      </div>
    ) : null

  const thumbUrl = book.cover_image_thumb ?? book.cover_image
  const originalUrl = book.cover_image
  const hasOriginal = originalUrl && originalUrl !== thumbUrl

  return (
    <div className="max-w-2xl">
      {thumbUrl && (
        <button
          type="button"
          onClick={() => hasOriginal && setShowOriginal(true)}
          className={`block w-full mb-4 text-left ${hasOriginal ? 'cursor-zoom-in' : 'cursor-default'}`}
        >
          <img
            src={resolveCoverUrl(thumbUrl)}
            alt={book.title}
            className="rounded-lg w-[340px] h-[480px] object-cover"
            onError={(e) => (e.currentTarget.style.display = 'none')}
          />
          {hasOriginal && (
            <p className="text-xs text-stone-500 mt-1">Click to view original</p>
          )}
        </button>
      )}
      {showOriginal && originalUrl && (
        <div
          className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
          onClick={() => setShowOriginal(false)}
          role="presentation"
        >
          <button
            type="button"
            className="absolute top-4 right-4 text-white text-2xl hover:opacity-80"
            onClick={() => setShowOriginal(false)}
          >
            ×
          </button>
          <img
            src={resolveCoverUrl(originalUrl)}
            alt={book.title}
            className="max-w-full max-h-[90vh] object-contain"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
      <h1 className="text-2xl font-bold text-amber-900 mb-2">{book.title}</h1>
      <p className="text-xl text-amber-800 mb-4">${book.price?.toFixed(2)}</p>
      <div className="space-y-0 text-stone-600 mb-4">
        <InfoRow label="Authors" value={authors.map((a: { name?: string }) => a.name).filter(Boolean).join(', ')} />
        <InfoRow label="Category" value={category?.subject_title ?? category?.dewey_code} />
        <InfoRow label="ISBN" value={book.isbn} />
        <InfoRow label="Publisher" value={book.publisher} />
        <InfoRow label="Year" value={book.publish_year} />
        <InfoRow label="Pages" value={book.pages} />
        <InfoRow label="Edition" value={book.edition_number} />
        <InfoRow label="Size" value={book.size} />
        <InfoRow label="Weight" value={book.weight != null ? `${book.weight} kg` : null} />
        <InfoRow
          label="Stock"
          value={
            book.stock_quantity !== undefined
              ? book.stock_quantity > 0
                ? `${book.stock_quantity} in stock`
                : 'Out of stock'
              : null
          }
        />
      </div>
      {book.description && <p className="text-stone-600 mb-4">{book.description}</p>}
      {userType === 'customer' && book.stock_quantity > 0 && (
        <button
          onClick={addToCart}
          className="px-6 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          Add to Cart
        </button>
      )}
      {userType !== 'customer' && (
        <p className="text-stone-500 text-sm">Login as customer to add to cart</p>
      )}
    </div>
  )
}
