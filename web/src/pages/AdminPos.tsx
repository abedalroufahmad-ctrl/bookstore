import { useState, useMemo, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, publishersPublic, type Book } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'
import { Pagination } from '../components/Pagination'
import { useSearchCommit } from '../hooks/useSearchCommit'
import { hasCover, resolveCoverUrl } from '../lib/utils'

function discountedPrice(book: Book): number {
  const discount = book.discount_percent ?? 0
  return book.price - (book.price * discount) / 100
}

function formatDateTime(raw: unknown): string {
  if (raw == null || raw === '') return '-'
  const d = new Date(String(raw))
  return Number.isNaN(d.getTime()) ? String(raw) : d.toLocaleString()
}

export function AdminPos() {
  const { t } = useTranslation()
  const { user } = useAuth()
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
  const [createdInvoice, setCreatedInvoice] = useState<any>(null)

  const { data: warehousesData } = useQuery({
    queryKey: ['admin-warehouses'],
    queryFn: async () => {
      const res = await admin.warehouses.list({ per_page: 100 })
      return res.data
    },
  })
  
  const warehousesRaw = (warehousesData as any)?.data ?? warehousesData
  const warehouses = Array.isArray(warehousesRaw)
    ? warehousesRaw
    : Array.isArray(warehousesRaw?.data)
      ? warehousesRaw.data
      : []

  const { data: publishersData } = useQuery({
    queryKey: ['public-publishers'],
    queryFn: async () => {
      const res = await publishersPublic.list({ per_page: 100 })
      return res.data
    },
  })
  const publishersRaw = (publishersData as any)?.data ?? publishersData
  const publishers = Array.isArray(publishersRaw)
    ? publishersRaw
    : Array.isArray(publishersRaw?.data)
      ? publishersRaw.data
      : []

  const filteredWarehouses = useMemo(() => {
    if (!publisherFilter) return warehouses
    return warehouses.filter((w: any) => String(w.publisher_id ?? w.publisher?._id ?? '') === publisherFilter)
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
      const payload = res.data as { data?: unknown } | undefined
      const invoice = payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object'
        ? payload.data
        : payload
      setCreatedInvoice(invoice)
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

  const subtotal = cart.reduce((acc, item) => acc + discountedPrice(item.book) * item.quantity, 0)

  if (createdInvoice) {
    const invoiceItems = Array.isArray(createdInvoice.items) ? createdInvoice.items : []
    const invoiceTotal = Number(createdInvoice.total ?? 0)
    const createdAt = formatDateTime(createdInvoice.created_at)
    return (
      <div className="space-y-4">
      <div className="flex flex-wrap gap-2 print:hidden">
        <span className="px-4 py-2 rounded-lg text-sm font-medium bg-amber-900 text-white">{t('admin.posTerminal')}</span>
        <Link to="/admin/pos/reports" className="px-4 py-2 rounded-lg text-sm font-medium bg-stone-100 text-stone-700 hover:bg-stone-200">
          {t('admin.posReports')}
        </Link>
      </div>
      <div className="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-stone-200">
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold mb-2">{t('admin.invoiceCreated', 'Invoice Created')}</h1>
          <p className="text-stone-600">#{createdInvoice._id}</p>
        </div>
        
        <div className="mb-6 space-y-2 text-sm border-b border-stone-200 pb-6">
          <p><strong>{t('admin.date')}:</strong> {createdAt}</p>
          <p><strong>{t('admin.warehouse')}:</strong> {warehouses.find((w: any) => String(w._id) === String(createdInvoice.warehouse_id))?.name ?? createdInvoice.warehouse_id}</p>
          {createdInvoice.customer_name && (
            <p><strong>{t('admin.customer')}:</strong> {createdInvoice.customer_name}</p>
          )}
          {!createdInvoice.customer_name && (
            <p><strong>{t('admin.customer')}:</strong> {t('admin.walkInCustomer')}</p>
          )}
        </div>

        <table className="w-full text-sm mb-6">
          <thead>
            <tr className="border-b border-stone-200">
              <th className="text-start py-2">{t('orders.itemTitleCol')}</th>
              <th className="text-end py-2">{t('orders.itemPriceCol')}</th>
            </tr>
          </thead>
          <tbody>
            {invoiceItems.map((item: { book_title?: string; book_id?: string; quantity?: number; price?: number }, i: number) => (
              <tr key={i} className="border-b border-stone-100">
                <td className="py-2">{item.book_title || item.book_id} <span className="text-stone-500">x{item.quantity}</span></td>
                <td className="py-2 text-end">${(Number(item.price) * Number(item.quantity ?? 0)).toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <th className="text-start py-4 text-lg">{t('orders.total')}</th>
              <th className="text-end py-4 text-lg">${invoiceTotal.toFixed(2)}</th>
            </tr>
          </tfoot>
        </table>

        <div className="flex gap-4 print:hidden">
          <button onClick={() => window.print()} className="flex-1 bg-stone-800 text-white py-2 rounded">
            {t('admin.print', 'Print')}
          </button>
          <button onClick={() => setCreatedInvoice(null)} className="flex-1 border border-stone-300 py-2 rounded">
            {t('admin.newSale', 'New Sale')}
          </button>
        </div>
      </div>
      </div>
    )
  }

  const booksPaginated = (booksData as any)?.data ?? booksData
  const booksList = Array.isArray(booksPaginated?.data) ? booksPaginated.data : (Array.isArray(booksPaginated) ? booksPaginated : [])
  const booksMeta = booksPaginated && 'current_page' in booksPaginated ? booksPaginated : null

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2 print:hidden">
        <span className="px-4 py-2 rounded-lg text-sm font-medium bg-amber-900 text-white">{t('admin.posTerminal')}</span>
        <Link to="/admin/pos/reports" className="px-4 py-2 rounded-lg text-sm font-medium bg-stone-100 text-stone-700 hover:bg-stone-200">
          {t('admin.posReports')}
        </Link>
      </div>
    <div className="flex flex-col lg:flex-row gap-6 h-[calc(100vh-140px)]">
      {/* Products Left Side */}
      <div className="flex-1 flex flex-col bg-white rounded-lg border border-stone-200 overflow-hidden">
        <div className="p-4 border-b border-stone-200 flex flex-wrap gap-4 items-center bg-stone-50">
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && commitSearch(searchInput)}
            placeholder={t('search.placeholder')}
            className="flex-1 min-w-[200px] px-3 py-2 border border-stone-300 rounded focus:ring-2 focus:ring-amber-500"
          />
          <button onClick={() => commitSearch(searchInput)} className="px-4 py-2 bg-stone-200 rounded hover:bg-stone-300">
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
                ? warehouses.filter((w: any) => String(w.publisher_id ?? w.publisher?._id ?? '') === nextPub)
                : warehouses
              const stillValid = list.some((w: any) => String(w._id) === String(warehouseFilter))
              if (!stillValid) {
                const own = list.find((w: any) => String(w._id) === String(defaultWarehouseId))
                setWarehouseFilter(own?._id ? String(own._id) : String(list[0]?._id ?? ''))
              }
            }}
            className="px-3 py-2 border border-stone-300 rounded max-w-xs"
          >
            <option value="">{t('admin.allPublishers')}</option>
            {publishers.map((p: any) => (
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
            className="px-3 py-2 border border-stone-300 rounded max-w-xs"
          >
            {filteredWarehouses.map((w: any) => (
              <option key={String(w._id)} value={String(w._id)}>{w.name}{w.publisher?.name ? ` — ${w.publisher.name}` : ''}</option>
            ))}
          </select>
        </div>
        <p className="px-4 py-2 text-xs text-stone-500 border-b border-stone-200">{t('admin.ownWarehouseDefault')}</p>

        <div className="flex-1 overflow-y-auto p-4 bg-stone-50/50">
          {isLoading ? (
            <div className="text-center py-8">{t('common.loading')}</div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
              {booksList.map((book: Book, i: number) => (
                <div 
                  key={book._id || `book-${i}`} 
                  onClick={() => addToCart(book)}
                  className={`bg-white p-3 rounded border border-stone-200 cursor-pointer hover:border-amber-400 hover:shadow-sm transition ${(book.stock_quantity ?? 0) <= 0 ? 'opacity-50 pointer-events-none' : ''}`}
                >
                  <div className="aspect-[3/4] bg-stone-100 rounded mb-2 overflow-hidden flex items-center justify-center">
                    {hasCover(book) ? (
                      <img src={resolveCoverUrl(book.cover_image_thumb || book.cover_image)} alt="" className="object-cover w-full h-full" />
                    ) : (
                      <span className="text-stone-300 font-serif">BOOK</span>
                    )}
                  </div>
                  <h3 className="text-sm font-medium line-clamp-2 leading-tight">{book.title}</h3>
                  <div className="mt-2 flex justify-between items-center">
                    <span className="font-semibold text-amber-900">${discountedPrice(book).toFixed(2)}</span>
                    <span className="text-xs text-stone-500">Qty: {book.stock_quantity}</span>
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
      <div className="w-full lg:w-96 bg-white rounded-lg border border-stone-200 flex flex-col h-full shrink-0">
        <div className="p-4 border-b border-stone-200 bg-amber-900 text-amber-50 rounded-t-lg">
          <h2 className="font-bold text-lg">{t('admin.currentSale', 'Current Sale')}</h2>
        </div>
        
        <div className="flex-1 overflow-y-auto p-2">
          {cart.length === 0 ? (
            <div className="text-center py-12 text-stone-400">
              {t('admin.cartEmpty', 'Cart is empty')}
            </div>
          ) : (
            <div className="space-y-2">
              {cart.map(item => {
                const finalPrice = discountedPrice(item.book)
                return (
                  <div key={item.book._id} className="flex gap-2 items-center p-2 hover:bg-stone-50 border border-transparent hover:border-stone-200 rounded">
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{item.book.title}</p>
                      <p className="text-xs text-stone-500">${finalPrice.toFixed(2)}</p>
                    </div>
                    <div className="flex items-center gap-1 shrink-0 bg-stone-100 rounded">
                      <button onClick={() => updateQuantity(item.book._id, item.quantity - 1)} className="w-7 h-7 flex items-center justify-center font-bold text-stone-600">-</button>
                      <span className="w-6 text-center text-sm font-medium">{item.quantity}</span>
                      <button 
                        onClick={() => updateQuantity(item.book._id, item.quantity + 1)} 
                        disabled={item.quantity >= (item.book.stock_quantity ?? 0)}
                        className="w-7 h-7 flex items-center justify-center font-bold text-stone-600 disabled:opacity-30"
                      >+</button>
                    </div>
                    <div className="w-16 text-end font-semibold text-sm shrink-0">
                      ${(finalPrice * item.quantity).toFixed(2)}
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        <div className="p-4 border-t border-stone-200 bg-stone-50 rounded-b-lg space-y-4">
          <div>
            <label className="text-xs font-medium text-stone-600 mb-1 block">{t('admin.customerNameOptional', 'Customer Name (Optional)')}</label>
            <input 
              type="text" 
              value={customerName}
              onChange={e => setCustomerName(e.target.value)}
              className="w-full px-3 py-2 border border-stone-300 rounded"
              placeholder={t('admin.walkInCustomer', 'Walk-in Customer')}
            />
          </div>
          
          <div className="flex justify-between items-center text-lg font-bold">
            <span>{t('orders.total')}</span>
            <span className="text-amber-900">${subtotal.toFixed(2)}</span>
          </div>

          <button
            onClick={() => checkoutMut.mutate()}
            disabled={cart.length === 0 || !warehouseFilter || checkoutMut.isPending}
            className="w-full py-3 bg-amber-900 text-amber-50 font-bold rounded hover:bg-amber-800 disabled:opacity-50"
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
