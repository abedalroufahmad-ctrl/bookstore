import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { cart } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

interface CartItem {
  book_id: string
  quantity: number
  price: number
  book?: {
    title?: string
    warehouse?: { id?: string; name?: string }
    discount_percent?: number
  }
}

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
    enabled: userType === 'customer' || userType === 'employee',
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

  if (userType !== 'customer' && userType !== 'employee') {
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

  const groupedItems = items.reduce((acc: Record<string, { name: string; items: CartItem[]; subtotal: number }>, item: CartItem) => {
    const warehouseId = item.book?.warehouse?.id ?? 'unknown'
    const warehouseName = item.book?.warehouse?.name ?? t('cart.unknownWarehouse', 'Unknown Warehouse')
    if (!acc[warehouseId]) {
      acc[warehouseId] = { name: warehouseName, items: [], subtotal: 0 }
    }
    acc[warehouseId].items.push(item)
    acc[warehouseId].subtotal += (item.price ?? 0) * (item.quantity ?? 0)
    return acc
  }, {})

  return (
    <div>
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">{t('cart.title')}</h1>
      
      <div className="mb-4 bg-blue-50 text-blue-800 p-4 rounded-lg text-sm border border-blue-200">
        ℹ️ {t('cart.warehouseSplitNotice', 'Items are grouped by warehouse. Each warehouse will be processed as a separate order.')}
      </div>

      <div className="space-y-8">
        {Object.entries(groupedItems).map(([warehouseId, group]) => (
          <div key={warehouseId} className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
            <div className="bg-stone-50 px-4 py-3 border-b border-stone-200 flex justify-between items-center">
              <h2 className="font-semibold text-stone-800 flex items-center gap-2">
                <span>🏭</span> {group.name}
              </h2>
              <span className="text-sm font-bold text-[var(--color-primary)]">
                ${group.subtotal.toFixed(2)}
              </span>
            </div>
            
            <div className="p-4 space-y-4">
              {group.items.map((item: CartItem) => (
                <div
                  key={item.book_id}
                  className="flex items-center justify-between pb-4 border-b border-stone-100 last:border-0 last:pb-0"
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
                      className="w-8 h-8 rounded bg-stone-200 hover:bg-stone-300 disabled:opacity-50 flex items-center justify-center"
                    >
                      −
                    </button>
                    <span className="w-6 text-center">{item.quantity}</span>
                    <button
                      onClick={() => updateQty.mutate({ bookId: item.book_id, qty: item.quantity + 1 })}
                      className="w-8 h-8 rounded bg-stone-200 hover:bg-stone-300 flex items-center justify-center"
                    >
                      +
                    </button>
                    <button
                      onClick={() => removeItem.mutate(item.book_id)}
                      className="ms-4 text-red-600 text-sm hover:underline"
                    >
                      {t('cart.remove')}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
      <div className="mt-8 flex justify-between items-center bg-stone-50 p-6 rounded-xl border border-stone-200">
        <div>
          <p className="text-sm text-stone-500 mb-1">{t('cart.grandTotal', 'Total across all warehouses')}</p>
          <p className="text-2xl font-bold text-[var(--color-text)]">${total.toFixed(2)}</p>
        </div>
        <Link
          to="/checkout"
          className="px-8 py-3 rounded-lg font-bold text-white hover:opacity-90 transition-opacity shadow-sm"
          style={{ background: 'var(--color-primary)' }}
        >
          {t('cart.checkout')}
        </Link>
      </div>
    </div>
  )
}
