import { useState, useMemo, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  admin,
  publishersPublic,
  type Book,
  type PaginatedResponse,
  type PosInvoice,
  type Publisher,
  type Warehouse,
} from '../lib/api'
import { useAuth } from '../contexts/authContextCore'
import { Pagination } from '../components/Pagination'
import { useSearchCommit } from '../hooks/useSearchCommit'
import { useSettings } from '../contexts/settingsContextCore'
import { formatWeight } from '../contexts/settingsUtils'
import { formatBookPrice, hasCover, resolveCoverUrl } from '../lib/utils'

function discountedPrice(book: Book): number {
  const discount = book.discount_percent ?? 0
  return book.price - (book.price * discount) / 100
}

function extractList<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[]
  if (!payload || typeof payload !== 'object') return []
  const data = (payload as { data?: unknown }).data
  if (Array.isArray(data)) return data as T[]
  if (data && typeof data === 'object') {
    const nested = (data as { data?: unknown }).data
    if (Array.isArray(nested)) return nested as T[]
  }
  return []
}

function formatDateTime(raw: unknown): string {
  if (raw == null || raw === '') return '-'
  const d = new Date(String(raw))
  return Number.isNaN(d.getTime()) ? String(raw) : d.toLocaleString()
}

export function AdminPos() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const { settings } = useSettings()
  const queryClient = useQueryClient()

  const defaultWarehouseId =
    user?.warehouse_id
    || user?.warehouse_ids?.[0]
    || ''

  const [page, setPage] = useState(1)
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()
  const [warehouseFilter, setWarehouseFilter] = useState(defaultWarehouseId ? String(defaultWarehouseId) : '')
  const [publisherFilter, setPublisherFilter] = useState('')

  const [cart, setCart] = useState<Array<{ book: Book; quantity: number }>>([])
  const [customerName, setCustomerName] = useState('')
  const [createdInvoice, setCreatedInvoice] = useState<PosInvoice | null>(null)

  const { data: warehousesData } = useQuery({
    queryKey: ['admin-warehouses'],
    queryFn: async () => {
      const res = await admin.warehouses.list({ per_page: 100 })
      return res.data
    },
  })
  
  const warehouses = useMemo(() => extractList<Warehouse>(warehousesData), [warehousesData])

  const { data: publishersData } = useQuery({
    queryKey: ['public-publishers'],
    queryFn: async () => {
      const res = await publishersPublic.list({ per_page: 100 })
      return res.data
    },
  })
  const publishers = useMemo(() => extractList<Publisher>(publishersData), [publishersData])
  const selectedWarehouse = warehouses.find((warehouse) => String(warehouse._id) === warehouseFilter)
  const selectedPublisherId = String(
    selectedWarehouse?.publisher_id ?? selectedWarehouse?.publisher?._id ?? '',
  )
  const currencySymbol =
    selectedWarehouse?.publisher?.public_settings?.currency_symbol
    ?? publishers.find((publisher) => publisher._id === selectedPublisherId)?.public_settings?.currency_symbol
  const currencyCode = selectedWarehouse?.publisher?.public_settings?.default_currency_code ?? 'USD'
  const formatPrice = (price: number) => formatBookPrice(price, currencySymbol ?? currencyCode, currencyCode)

  const filteredWarehouses = useMemo(() => {
    if (!publisherFilter) return warehouses
    return warehouses.filter((w) => String(w.publisher_id ?? w.publisher?._id ?? '') === publisherFilter)
  }, [warehouses, publisherFilter])

  useEffect(() => {
    if (warehouseFilter) return
    if (defaultWarehouseId) {
      setWarehouseFilter(String(defaultWarehouseId))
      return
    }
    if (warehouses.length > 0) {
      setWarehouseFilter(String(warehouses[0]._id))
    }
  }, [defaultWarehouseId, warehouseFilter, warehouses])

  useEffect(() => {
    if (!filteredWarehouses.length || !warehouseFilter) return
    const stillValid = filteredWarehouses.some((w: { _id?: string }) => String(w._id) === String(warehouseFilter))
    if (!stillValid) {
      const own = filteredWarehouses.find((w: { _id?: string }) => String(w._id) === String(defaultWarehouseId))
      setWarehouseFilter(String(own?._id ?? filteredWarehouses[0]?._id ?? ''))
      setPage(1)
      setCart([])
    }
  }, [filteredWarehouses, warehouseFilter, defaultWarehouseId])

  const { data: booksData, isLoading } = useQuery({
    queryKey: ['pos-books', page, committedSearch, warehouseFilter, publisherFilter],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 12 }
      if (committedSearch) params.search = committedSearch
      if (warehouseFilter) params.warehouse_id = warehouseFilter
      if (publisherFilter) params.publisher_id = publisherFilter
      const res = await admin.pos.books(params)
      return res.data
    },
    enabled: Boolean(warehouseFilter),
  })

  const checkoutMut = useMutation({
    mutationFn: async () => {
      const items = cart.map(i => ({ book_id: i.book._id, quantity: i.quantity }))
      return admin.pos.createInvoice({
        items,
        warehouse_id: warehouseFilter,
        customer_name: customerName.trim() || undefined,
      })
    },
    onSuccess: (res) => {
      setCreatedInvoice(res.data.data)
      setCart([])
      setCustomerName('')
      void queryClient.invalidateQueries({ queryKey: ['pos-books'] })
      void queryClient.invalidateQueries({ queryKey: ['pos-invoices'] })
      void queryClient.invalidateQueries({ queryKey: ['pos-reports'] })
    },
  })

  const addToCart = (book: Book) => {
    if ((book.stock_quantity ?? 0) <= 0) return
    setCart(prev => {
      const existing = prev.find(i => i.book._id === book._id)
      if (existing) {
        if (existing.quantity >= (book.stock_quantity ?? 0)) return prev
        return prev.map(i => i.book._id === book._id ? { ...i, quantity: i.quantity + 1 } : i)
      }
      return [...prev, { book, quantity: 1 }]
    })
  }

  const updateQuantity = (bookId: string, q: number) => {
    if (q <= 0) {
      setCart(prev => prev.filter(i => i.book._id !== bookId))
      return
    }
    setCart(prev => prev.map(i => i.book._id === bookId ? { ...i, quantity: q } : i))
  }

  const booksSubtotal = cart.reduce((acc, item) => acc + discountedPrice(item.book) * item.quantity, 0)
  const shippingFee = settings.invoice_shipping_fee
  const cartTotal = booksSubtotal + shippingFee

  if (createdInvoice) {
    const invoiceItems = Array.isArray(createdInvoice.items) ? createdInvoice.items : []
    const invoiceBooksSubtotal = Number(
      createdInvoice.books_subtotal
      ??       invoiceItems.reduce<number>(
        (s: number, i: { price?: number; quantity?: number }) => s + Number(i.price) * Number(i.quantity ?? 0),
        0,
      ),
    )
    const invoiceShippingFee = Number(createdInvoice.shipping_fee ?? 0)
    const invoiceTotal = Number(createdInvoice.total ?? 0)
    const invoiceTotalWeightG = invoiceItems.reduce((s: number, item) => {
      const unit = Number(item.weight)
      if (!Number.isFinite(unit) || unit <= 0) return s
      return s + unit * Number(item.quantity ?? 0)
    }, 0)
    const invoiceTotalWeightLabel = invoiceTotalWeightG > 0
      ? formatWeight(invoiceTotalWeightG, settings.weight_unit)
      : null
    const createdAt = formatDateTime(createdInvoice.created_at)
    const createdWarehouse =
      createdInvoice.warehouse
      ?? warehouses.find((w) => String(w._id) === String(createdInvoice.warehouse_id))
    const invoicePublisherId = String(
      createdWarehouse?.publisher_id ?? createdWarehouse?.publisher?._id ?? '',
    )
    const invoiceCurrencySymbol =
      createdWarehouse?.publisher?.public_settings?.currency_symbol
      ?? publishers.find((publisher) => publisher._id === invoicePublisherId)?.public_settings?.currency_symbol
    const invoiceCurrencyCode = createdInvoice.currency_code ?? 'USD'
    const formatInvoicePrice = (price: number) =>
      formatBookPrice(price, invoiceCurrencySymbol ?? invoiceCurrencyCode, invoiceCurrencyCode)
    const createdPublisherName = createdWarehouse?.publisher?.name
    return (
      <div className="space-y-4">
      <div className="flex flex-wrap gap-2 print:hidden">
        <span className="px-4 py-2 rounded-lg text-sm font-medium bg-navy-900 text-white">{t('admin.posTerminal')}</span>
        <Link to="/admin/pos/reports" className="px-4 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
          {t('admin.posReports')}
        </Link>
      </div>
      <div className="pos-invoice-sheet max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-slate-200 print:max-w-none print:mx-0 print:shadow-none print:border-0 print:rounded-none">
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold mb-2">{t('admin.invoiceCreated', 'Invoice Created')}</h1>
          <p className="text-slate-600">#{createdInvoice._id}</p>
        </div>
        
        <div className="mb-6 space-y-2 text-sm border-b border-slate-200 pb-6">
          <p><strong>{t('admin.date')}:</strong> {createdAt}</p>
          <p><strong>{t('admin.warehouse')}:</strong> {createdWarehouse?.name ?? createdInvoice.warehouse_id}</p>
          {createdPublisherName && (
            <p><strong>{t('admin.publisher')}:</strong> {createdPublisherName}</p>
          )}
          {createdInvoice.customer_name && (
            <p><strong>{t('admin.customer')}:</strong> {createdInvoice.customer_name}</p>
          )}
          {!createdInvoice.customer_name && (
            <p><strong>{t('admin.customer')}:</strong> {t('admin.walkInCustomer')}</p>
          )}
        </div>

        <table className="w-full text-sm mb-6">
          <thead>
            <tr className="border-b border-slate-200">
              <th className="text-start py-2">{t('orders.itemTitleCol')}</th>
              <th className="text-end py-2">{t('orders.itemWeightCol')}</th>
              <th className="text-end py-2">{t('orders.itemPriceCol')}</th>
            </tr>
          </thead>
          <tbody>
            {invoiceItems.map((item, i) => {
              const lineW = Number(item.weight) > 0 ? Number(item.weight) * Number(item.quantity ?? 0) : 0
              return (
                <tr key={i} className="border-b border-slate-100">
                  <td className="py-2">{item.book_title || item.book_id} <span className="text-slate-500">x{item.quantity}</span></td>
                  <td className="py-2 text-end text-slate-600">
                    {lineW > 0 ? formatWeight(lineW, settings.weight_unit) : '—'}
                  </td>
                  <td className="py-2 text-end">{formatInvoicePrice(Number(item.price) * Number(item.quantity ?? 0))}</td>
                </tr>
              )
            })}
          </tbody>
          <tfoot>
            <tr className="text-slate-600">
              <td className="text-start py-2" colSpan={2}>{t('orders.booksSubtotal', 'Books subtotal')}</td>
              <td className="text-end py-2">{formatInvoicePrice(invoiceBooksSubtotal)}</td>
            </tr>
            <tr className="text-slate-600">
              <td className="text-start py-2" colSpan={2}>{t('orders.shippingFee', 'Shipping fee')}</td>
              <td className="text-end py-2">{formatInvoicePrice(invoiceShippingFee)}</td>
            </tr>
            <tr>
              <th className="text-start py-4 text-lg" colSpan={2}>{t('orders.total')}</th>
              <th className="text-end py-4 text-lg">{formatInvoicePrice(invoiceTotal)}</th>
            </tr>
            {invoiceTotalWeightLabel && (
              <tr className="text-slate-600">
                <td className="text-start py-2" colSpan={2}>{t('orders.totalWeight')}</td>
                <td className="text-end py-2 font-medium">{invoiceTotalWeightLabel}</td>
              </tr>
            )}
          </tfoot>
        </table>

        <div className="flex gap-4 print:hidden">
          <button onClick={() => window.print()} className="flex-1 bg-slate-800 text-white py-2 rounded">
            {t('admin.print', 'Print')}
          </button>
          <button onClick={() => setCreatedInvoice(null)} className="flex-1 border border-slate-300 py-2 rounded">
            {t('admin.newSale', 'New Sale')}
          </button>
        </div>
      </div>
      </div>
    )
  }

  const booksPaginated = booksData?.data as PaginatedResponse<Book> | undefined
  const booksList = booksPaginated?.data ?? []
  const booksMeta = booksPaginated ?? null

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2 print:hidden">
        <span className="px-4 py-2 rounded-lg text-sm font-medium bg-navy-900 text-white">{t('admin.posTerminal')}</span>
        <Link to="/admin/pos/reports" className="px-4 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
          {t('admin.posReports')}
        </Link>
      </div>
    <div className="flex flex-col lg:flex-row gap-6 h-[calc(100vh-140px)]">
      {/* Products Left Side */}
      <div className="flex-1 flex flex-col bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div className="p-4 border-b border-slate-200 flex flex-wrap gap-4 items-center bg-slate-50">
          <input
            type="text"
            value={searchInput}
            onChange={(e) => {
              const next = e.target.value
              setSearchInput(next)
              if (next.endsWith(' ')) {
                window.setTimeout(() => commitSearch(next), 0)
              }
            }}
            onKeyDown={(e) => e.key === 'Enter' && commitSearch(searchInput)}
            placeholder={t('search.placeholder')}
            className="flex-1 min-w-[200px] px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-navy-500"
          />
          <button onClick={() => commitSearch(searchInput)} className="px-4 py-2 bg-slate-200 rounded hover:bg-slate-300">
            {t('search.search')}
          </button>
          
          <select
            value={publisherFilter}
            onChange={(e) => {
              const nextPub = e.target.value
              setPublisherFilter(nextPub)
              setPage(1)
              setCart([])
              const list = nextPub
                ? warehouses.filter((w) => String(w.publisher_id ?? w.publisher?._id ?? '') === nextPub)
                : warehouses
                const stillValid = list.some((w) => String(w._id) === String(warehouseFilter))
              if (!stillValid) {
                  const own = list.find((w) => String(w._id) === String(defaultWarehouseId))
                setWarehouseFilter(own?._id ? String(own._id) : String(list[0]?._id ?? ''))
              }
            }}
            className="px-3 py-2 border border-slate-300 rounded max-w-xs"
          >
            <option value="">{t('admin.allPublishers')}</option>
            {publishers.map((p) => (
              <option key={p._id} value={p._id}>{p.name}</option>
            ))}
          </select>

          <select
            value={warehouseFilter}
            onChange={e => {
              setWarehouseFilter(e.target.value)
              setCart([])
              setPage(1)
            }}
            className="px-3 py-2 border border-slate-300 rounded max-w-xs"
          >
            {filteredWarehouses.map((w) => (
              <option key={String(w._id)} value={String(w._id)}>{w.name}{w.publisher?.name ? ` — ${w.publisher.name}` : ''}</option>
            ))}
          </select>
        </div>
        <p className="px-4 py-2 text-xs text-slate-500 border-b border-slate-200">{t('admin.ownWarehouseDefault')}</p>

        <div className="flex-1 overflow-y-auto p-4 bg-slate-50/50">
          {isLoading ? (
            <div className="text-center py-8">{t('common.loading')}</div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
              {booksList.map((book: Book, i: number) => (
                <div 
                  key={book._id || `book-${i}`} 
                  onClick={() => addToCart(book)}
                  className={`bg-white p-3 rounded border border-slate-200 cursor-pointer hover:border-navy-400 hover:shadow-sm transition ${(book.stock_quantity ?? 0) <= 0 ? 'opacity-50 pointer-events-none' : ''}`}
                >
                  <div className="aspect-[3/4] bg-slate-100 rounded mb-2 overflow-hidden flex items-center justify-center">
                    {hasCover(book) ? (
                      <img loading="lazy" decoding="async" src={resolveCoverUrl(book.cover_image_thumb || book.cover_image)} alt="" className="object-cover w-full h-full" />
                    ) : (
                      <span className="text-slate-300 font-serif">{t('admin.bookPlaceholder')}</span>
                    )}
                  </div>
                  <h3 className="text-sm font-medium line-clamp-2 leading-tight">{book.title}</h3>
                  {book.weight != null && Number(book.weight) > 0 && (
                    <p className="text-xs text-slate-500 mt-1">
                      {t('bookDetail.weight')}: {formatWeight(book.weight, settings.weight_unit)}
                    </p>
                  )}
                  <div className="mt-2 flex justify-between items-center">
                    <span className="font-semibold text-navy-900">{formatPrice(discountedPrice(book))}</span>
                    <span className="text-xs text-slate-500">Qty: {book.stock_quantity}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
          {booksMeta && (
             <div className="mt-6 flex justify-center pb-4">
              <Pagination
                currentPage={booksMeta.current_page}
                lastPage={booksMeta.last_page}
                total={booksMeta.total}
                perPage={booksMeta.per_page}
                onPageChange={setPage}
              />
            </div>
          )}
        </div>
      </div>

      {/* Cart Right Side */}
      <div className="w-full lg:w-96 bg-white rounded-lg border border-slate-200 flex flex-col h-full shrink-0">
        <div className="p-4 border-b border-slate-200 bg-navy-900 text-navy-50 rounded-t-lg">
          <h2 className="font-bold text-lg">{t('admin.currentSale', 'Current Sale')}</h2>
        </div>
        
        <div className="flex-1 overflow-y-auto p-2">
          {cart.length === 0 ? (
            <div className="text-center py-12 text-slate-400">
              {t('admin.cartEmpty', 'Cart is empty')}
            </div>
          ) : (
            <div className="space-y-2">
              {cart.map(item => {
                const finalPrice = discountedPrice(item.book)
                return (
                  <div key={item.book._id} className="flex gap-2 items-center p-2 hover:bg-slate-50 border border-transparent hover:border-slate-200 rounded">
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{item.book.title}</p>
                      <p className="text-xs text-slate-500">{formatPrice(finalPrice)}</p>
                    </div>
                    <div className="flex items-center gap-1 shrink-0 bg-slate-100 rounded">
                      <button onClick={() => updateQuantity(item.book._id, item.quantity - 1)} className="w-7 h-7 flex items-center justify-center font-bold text-slate-600">-</button>
                      <span className="w-6 text-center text-sm font-medium">{item.quantity}</span>
                      <button 
                        onClick={() => updateQuantity(item.book._id, item.quantity + 1)} 
                        disabled={item.quantity >= (item.book.stock_quantity ?? 0)}
                        className="w-7 h-7 flex items-center justify-center font-bold text-slate-600 disabled:opacity-30"
                      >+</button>
                    </div>
                    <div className="w-16 text-end font-semibold text-sm shrink-0">
                      {formatPrice(finalPrice * item.quantity)}
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        <div className="p-4 border-t border-slate-200 bg-slate-50 rounded-b-lg space-y-4">
          <div>
            <label className="text-xs font-medium text-slate-600 mb-1 block">{t('admin.customerNameOptional', 'Customer Name (Optional)')}</label>
            <input 
              type="text" 
              value={customerName}
              onChange={e => setCustomerName(e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded"
              placeholder={t('admin.walkInCustomer', 'Walk-in Customer')}
            />
          </div>
          
          <div className="space-y-1 text-sm text-slate-600">
            <div className="flex justify-between">
              <span>{t('orders.booksSubtotal', 'Books subtotal')}</span>
              <span>{formatPrice(booksSubtotal)}</span>
            </div>
            <div className="flex justify-between">
              <span>{t('orders.shippingFee', 'Shipping fee')}</span>
              <span>{formatPrice(shippingFee)}</span>
            </div>
            <div className="flex justify-between items-center text-lg font-bold text-slate-900 pt-1">
              <span>{t('orders.total')}</span>
              <span className="text-navy-900">{formatPrice(cartTotal)}</span>
            </div>
          </div>

          <button
            onClick={() => checkoutMut.mutate()}
            disabled={cart.length === 0 || !warehouseFilter || checkoutMut.isPending}
            className="w-full py-3 bg-navy-900 text-navy-50 font-bold rounded hover:bg-navy-800 disabled:opacity-50"
          >
            {checkoutMut.isPending ? t('common.loading') : t('admin.completeSale')}
          </button>
          {checkoutMut.isError && (
            <p className="text-xs text-red-600 text-center">
              {(checkoutMut.error as { response?: { data?: { message?: string } } })?.response?.data?.message
                ?? t('common.error')}
            </p>
          )}
          {!warehouseFilter && cart.length > 0 && (
             <p className="text-xs text-red-600 text-center">{t('admin.selectWarehouseFirst')}</p>
          )}
        </div>
      </div>
    </div>
    </div>
  )
}
