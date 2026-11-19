import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { cart, type CartItemDetail } from '../lib/api'
import { useAuth } from '../contexts/authContextCore'
import { useCurrencyPreference } from '../hooks/useCurrencyPreference'
import { formatBookPrice } from '../lib/utils'

export function CartPage() {
  const { userType } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { t } = useTranslation()
  const { currencyCode } = useCurrencyPreference()

  const { data, isLoading } = useQuery({
    queryKey: ['cart', currencyCode],
    queryFn: async () => {
      const res = await cart.get(currencyCode)
      return res.data
    },
    enabled: userType === 'customer' || userType === 'employee',
  })

  const removeItem = useMutation({
    mutationFn: (bookId: string) => cart.removeItem(bookId, currencyCode),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  const updateQty = useMutation({
    mutationFn: ({ bookId, qty, currency }: { bookId: string; qty: number; currency: string }) =>
      cart.updateItem(bookId, qty, currency),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  if (userType !== 'customer' && userType !== 'employee') {
    navigate('/login')
    return null
  }

  if (isLoading) return <div className="text-center py-12">{t('cart.loading')}</div>

  const items: CartItemDetail[] = data?.data?.items ?? []
  const total = data?.data?.total

  if (items.length === 0) {
    return (
      <div className="text-center py-12">
        <p className="text-slate-600 mb-4">{t('cart.empty')}</p>
        <Link to="/books" className="text-[var(--color-primary)] font-medium">{t('cart.browseBooks')}</Link>
      </div>
    )
  }

  const lineCurrency = (item: CartItemDetail) =>
    (item.currency_available ? currencyCode : item.book?.warehouse?.currency_code || item.currency_code || currencyCode).toUpperCase()

  const groupedItems = items.reduce((acc: Record<string, { name: string; warehouseCurrency: string; currencyCode: string; items: CartItemDetail[]; subtotal: number; priced: boolean }>, item) => {
    const warehouseId = item.book?.warehouse?.id ?? 'unknown'
    const warehouseName = item.book?.warehouse?.name ?? t('cart.unknownWarehouse', 'Unknown Warehouse')
    const itemCurrency = lineCurrency(item)
    if (!acc[warehouseId]) {
      acc[warehouseId] = {
        name: warehouseName,
        warehouseCurrency: (item.book?.warehouse?.currency_code || itemCurrency).toUpperCase(),
        currencyCode: itemCurrency,
        items: [],
        subtotal: 0,
        priced: true,
      }
    }
    acc[warehouseId].items.push(item)
    if (item.price == null || item.subtotal == null || lineCurrency(item) !== acc[warehouseId].currencyCode) {
      acc[warehouseId].priced = false
    } else {
      acc[warehouseId].subtotal += item.subtotal
    }
    return acc
  }, {})

  const checkoutBlocked = items.some((item) => item.price == null)
  const mixedWarehouseCurrencies = new Set(items.map((item) => lineCurrency(item))).size > 1

  return (
    <div>
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">{t('cart.title')}</h1>
      
      <div className="mb-4 bg-blue-50 text-blue-800 p-4 rounded-lg text-sm border border-blue-200">
        ℹ️ {t('cart.warehouseSplitNotice', 'Items are grouped by warehouse. Each warehouse will be processed as a separate order.')}
      </div>

      <div className="space-y-8">
        {Object.entries(groupedItems).map(([warehouseId, group]) => (
          <div key={warehouseId} className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div className="bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center">
              <h2 className="font-semibold text-slate-800 flex items-center gap-2">
                <span>🏭</span> {group.name}
                <span className="text-xs font-medium text-slate-500">{group.warehouseCurrency}</span>
              </h2>
              <span className="text-sm font-bold text-[var(--color-primary)]">
                {group.priced
                  ? formatBookPrice(group.subtotal, group.currencyCode, group.currencyCode)
                  : t('cart.currencyUnavailable', 'Unavailable in {{currency}}', { currency: group.currencyCode })}
              </span>
            </div>
            
            <div className="p-4 space-y-4">
              {group.items.map((item) => (
                <div
                  key={item.book_id}
                  className="flex items-center justify-between pb-4 border-b border-slate-100 last:border-0 last:pb-0"
                >
                  <div>
                    <p className="font-medium">{item.book?.title ?? t('common.book')}</p>
                    <div className="flex items-center gap-2">
                      {item.price == null ? (
                        <p className="text-sm text-red-600">
                          {t('cart.currencyUnavailable', 'Unavailable in {{currency}}', { currency: lineCurrency(item) })}
                        </p>
                      ) : (
                        <p className="text-sm text-slate-500">
                          {formatBookPrice(item.price, lineCurrency(item), lineCurrency(item))} × {item.quantity}
                        </p>
                      )}
                      {item.book?.discount_percent && item.book.discount_percent > 0 ? (
                        <span className="text-[10px] bg-[var(--color-primary-light)] text-[var(--color-primary)] px-1 rounded font-bold">
                          {t('discount.special', { percent: item.book.discount_percent })}
                        </span>
                      ) : null}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => updateQty.mutate({ bookId: item.book_id, qty: item.quantity - 1, currency: lineCurrency(item) })}
                      disabled={item.quantity <= 1 || item.price == null}
                      className="w-8 h-8 rounded bg-slate-200 hover:bg-slate-300 disabled:opacity-50 flex items-center justify-center"
                    >
                      −
                    </button>
                    <span className="w-6 text-center">{item.quantity}</span>
                    <button
                      onClick={() => updateQty.mutate({ bookId: item.book_id, qty: item.quantity + 1, currency: lineCurrency(item) })}
                      disabled={item.price == null}
                      className="w-8 h-8 rounded bg-slate-200 hover:bg-slate-300 disabled:opacity-50 flex items-center justify-center"
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
      <div className="mt-8 flex justify-between items-center bg-slate-50 p-6 rounded-xl border border-slate-200">
        <div>
          <p className="text-sm text-slate-500 mb-1">{t('cart.grandTotal', 'Total across all warehouses')}</p>
          <p className="text-2xl font-bold text-[var(--color-text)]">
            {total == null ? '—' : formatBookPrice(total, currencyCode, currencyCode)}
          </p>
          {mixedWarehouseCurrencies ? (
            <p className="text-sm text-slate-500 mt-1">{t('cart.warehouseCurrencyNote')}</p>
          ) : null}
        </div>
        <Link
          to="/checkout"
          aria-disabled={checkoutBlocked}
          className={`px-8 py-3 rounded-lg font-bold text-white transition-opacity shadow-sm ${checkoutBlocked ? 'opacity-50 pointer-events-none' : 'hover:opacity-90'}`}
          style={{ background: 'var(--color-primary)' }}
        >
          {t('cart.checkout')}
        </Link>
      </div>
    </div>
  )
}
