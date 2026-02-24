import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { books } from '../lib/api'

export function BookList() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['books'],
    queryFn: async () => {
      const res = await books.list()
      return res.data
    },
  })

  if (isLoading) return <div className="text-center py-12">Loading...</div>
  if (error) return <div className="text-center py-12 text-red-600">Failed to load books</div>

  const paginated = data?.data
  const items = Array.isArray(paginated) ? paginated : (paginated?.data ?? [])

  const apiBase = import.meta.env.VITE_API_URL || '/api/v1'
  const resolveCoverUrl = (path: string) =>
    path.startsWith('http://') || path.startsWith('https://')
      ? path
      : `${apiBase.replace(/\/api\/v1$/, '')}${path.startsWith('/') ? path : `/${path}`}`

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">Browse Books</h1>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        {items.map((book: { _id: string; title: string; price: number; stock_quantity?: number; cover_image_thumb?: string; cover_image?: string }) => {
          const thumbUrl = book.cover_image_thumb ?? book.cover_image
          return (
          <div
            key={book._id}
            className="bg-white rounded-lg shadow border border-stone-200 overflow-hidden hover:shadow-md transition flex flex-row min-h-[140px]"
          >
            <div className="p-4 flex-1 min-w-0">
              <Link to={`/books/${book._id}`} className="font-medium text-amber-900 hover:underline">
                {book.title}
              </Link>
              <p className="mt-1 text-stone-600">${book.price?.toFixed(2)}</p>
              {book.stock_quantity !== undefined && (
                <p className="text-sm text-stone-500">
                  {book.stock_quantity > 0 ? `${book.stock_quantity} in stock` : 'Out of stock'}
                </p>
              )}
              <Link
                to={`/books/${book._id}`}
                className="mt-2 inline-block text-sm text-amber-700 font-medium"
              >
                View details →
              </Link>
            </div>
            {thumbUrl && (
              <Link to={`/books/${book._id}`} className="flex-shrink-0 w-24 sm:w-32 flex self-stretch">
                <img
                  src={resolveCoverUrl(thumbUrl)}
                  alt={book.title}
                  className="w-full h-full object-cover object-center"
                  onError={(e) => (e.currentTarget.style.display = 'none')}
                />
              </Link>
            )}
          </div>
          )
        })}
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-12">No books found. Add some in the admin panel.</p>
      )}
    </div>
  )
}
