import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { cart } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

export function CartPage() {
  const { userType } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  const { data, isLoading } = useQuery({
    queryKey: ['cart'],
    queryFn: async () => {
      const res = await cart.get()
      return res.data
    },
    enabled: userType === 'customer',
  })

  const removeItem = useMutation({
    mutationFn: (bookId: string) => cart.removeItem(bookId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  const updateQty = useMutation({
    mutationFn: ({ bookId, qty }: { bookId: string; qty: number }) =>
      cart.updateItem(bookId, qty),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  if (userType !== 'customer') {
    navigate('/login')
    return null
  }

  if (isLoading) return <div className="text-center py-12">{t('cart.loading')}</div>

  const items = data?.data?.items ?? []
  const total = data?.data?.total ?? 0

  if (items.length === 0) {
    return (
      <div className="text-center py-12">
        <p className="text-stone-600 mb-4">{t('cart.empty')}</p>
        <Link to="/books" className="text-[var(--color-primary)] font-medium">{t('cart.browseBooks')}</Link>
      </div>
    )
  }

  return (
    <div>
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">{t('cart.title')}</h1>
      <div className="space-y-4">
        {items.map((item: { book_id: string; quantity: number; price: number; book?: { title: string; discount_percent?: number } }) => (
          <div
            key={item.book_id}
            className="flex items-center justify-between bg-white p-4 rounded-lg border border-stone-200"
          >
            <div>
              <p className="font-medium">{item.book?.title ?? t('common.book')}</p>
              <div className="flex items-center gap-2">
                <p className="text-sm text-stone-500">${item.price?.toFixed(2)} × {item.quantity}</p>
                {item.book?.discount_percent && item.book.discount_percent > 0 ? (
                  <span className="text-[10px] bg-[var(--color-primary-light)] text-[var(--color-primary)] px-1 rounded font-bold">
                    {t('discount.special', { percent: item.book.discount_percent })}
                  </span>
                ) : null}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => updateQty.mutate({ bookId: item.book_id, qty: item.quantity - 1 })}
                disabled={item.quantity <= 1}
                className="w-8 h-8 rounded bg-stone-200 hover:bg-stone-300 disabled:opacity-50"
              >
                −
              </button>
              <span>{item.quantity}</span>
              <button
                onClick={() => updateQty.mutate({ bookId: item.book_id, qty: item.quantity + 1 })}
                className="w-8 h-8 rounded bg-stone-200 hover:bg-stone-300"
              >
                +
              </button>
              <button
                onClick={() => removeItem.mutate(item.book_id)}
                className="ms-2 text-red-600 text-sm hover:underline"
              >
                {t('cart.remove')}
              </button>
            </div>
          </div>
        ))}
      </div>
      <div className="mt-6 flex justify-between items-center">
        <p className="text-lg font-bold">{t('cart.total', { amount: total.toFixed(2) })}</p>
        <Link
          to="/checkout"
          className="px-6 py-2.5 rounded-lg font-medium text-white hover:opacity-90 transition-opacity"
          style={{ background: 'var(--color-primary)' }}
        >
          {t('cart.checkout')}
        </Link>
      </div>
    </div>
  )
}
