import { useEffect, useRef, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { AdminListSearchBar } from '../components/AdminListSearchBar'
import { useSearchCommit } from '../hooks/useSearchCommit'
import type { Book, Warehouse } from '../lib/api'

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return Array.isArray(d) ? d : []
}

export function AdminBooks() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [deleteId, setDeleteId] = useState<string | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set())
  const headerCheckboxRef = useRef<HTMLInputElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [page, setPage] = useState(1)
  const [conditionFilter, setConditionFilter] = useState('')
  const [visibilityFilter, setVisibilityFilter] = useState('')
  const [importWarehouseId, setImportWarehouseId] = useState('')
  const [importMessage, setImportMessage] = useState('')
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()

  useEffect(() => {
    setPage(1)
  }, [committedSearch, conditionFilter, visibilityFilter])

  const { data: warehousesData } = useQuery({
    queryKey: ['admin-warehouses-for-import'],
    queryFn: async () => {
      const res = await admin.warehouses.list({ per_page: 100 })
      return res.data
    },
  })

  const warehouseList = extractList<Warehouse>(warehousesData)

  const { data, isLoading, isFetching, error } = useQuery({
    queryKey: ['admin-books', page, committedSearch, conditionFilter, visibilityFilter],
    queryFn: async () => {
      const res = await admin.books.list({
        page,
        per_page: 32,
        ...(committedSearch ? { search: committedSearch } : {}),
        ...(conditionFilter ? { condition: conditionFilter } : {}),
        ...(visibilityFilter === 'visible' ? { is_visible: 1 } : {}),
        ...(visibilityFilter === 'hidden' ? { is_visible: 0 } : {}),
        ...(visibilityFilter === 'sold' ? { is_sold: 1 } : {}),
      })
      return res.data
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.books.delete(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      queryClient.invalidateQueries({ queryKey: ['admin-books-without-cover'] })
      setDeleteId(null)
      setSelectedIds((prev) => {
        const next = new Set(prev)
        next.delete(id)
        return next
      })
    },
  })

  const bulkDeleteMutation = useMutation({
    mutationFn: async (ids: string[]) => {
      await Promise.all(ids.map((id) => admin.books.delete(id)))
    },
    onSuccess: (_, ids) => {
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      queryClient.invalidateQueries({ queryKey: ['admin-books-without-cover'] })
      setSelectedIds((prev) => {
        const next = new Set(prev)
        ids.forEach((id) => next.delete(id))
        return next
      })
    },
  })

  const importMutation = useMutation({
    mutationFn: async (file: File) => {
      if (!importWarehouseId) {
        throw new Error(t('admin.selectStoreFirst'))
      }
      const res = await admin.books.import(file, importWarehouseId, true)
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      const payload = res?.data
      setImportMessage(
        t('admin.importResult', {
          created: payload?.created ?? 0,
          skipped: payload?.skipped ?? 0,
          errors: payload?.errors ?? 0,
        })
      )
      if (fileInputRef.current) fileInputRef.current.value = ''
    },
    onError: (err: { message?: string; response?: { data?: { message?: string } } }) => {
      setImportMessage(err?.response?.data?.message ?? err.message ?? t('common.error'))
    },
  })

  const handleDelete = (book: Book) => {
    if (window.confirm(t('admin.deleteBookConfirm', { title: book.title }))) {
      deleteMutation.mutate(book._id)
      setDeleteId(book._id)
    }
  }

  const handleBulkDelete = () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return
    if (
      window.confirm(
        t('admin.deleteBooksBulkConfirm', {
          count: ids.length,
        })
      )
    ) {
      bulkDeleteMutation.mutate(ids)
    }
  }

  const queryErrorMessage =
    (error as { response?: { data?: { message?: string } } } | null)?.response?.data?.message ??
    (data && 'success' in data && data.success === false ? data.message : undefined)

  const paginated = data?.data
  const items = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  const pageIds = items.map((b: Book) => b._id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id))
  const someSelected = pageIds.some((id) => selectedIds.has(id))

  useEffect(() => {
    const el = headerCheckboxRef.current
    if (el) {
      el.indeterminate = someSelected && !allSelected
    }
  }, [someSelected, allSelected])

  if (isLoading && !data) return <div className="text-center py-12">{t('common.loading')}</div>

  const toggleSelectAllOnPage = () => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (allSelected) {
        pageIds.forEach((id) => next.delete(id))
      } else {
        pageIds.forEach((id) => next.add(id))
      }
      return next
    })
  }

  const toggleRow = (id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const selectedCount = selectedIds.size
  const deletingBulk = bulkDeleteMutation.isPending

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.books')}</h1>
        <Link
          to="/admin/books/new"
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 shrink-0 self-start"
        >
          {t('admin.addBook')}
        </Link>
      </div>

      <div className="mb-6 rounded-lg border border-stone-200 bg-white p-4 space-y-3">
        <h2 className="text-sm font-semibold text-stone-800">{t('admin.importExcel')}</h2>
        <p className="text-xs text-stone-500">{t('admin.importExcelHint')}</p>
        <div className="flex flex-wrap gap-3 items-end">
          <div>
            <label className="block text-xs font-medium text-stone-600 mb-1">{t('admin.store')}</label>
            <select
              value={importWarehouseId}
              onChange={(e) => setImportWarehouseId(e.target.value)}
              className="px-3 py-2 border border-stone-300 rounded-lg text-sm"
            >
              <option value="">{t('admin.selectStore')}</option>
              {warehouseList.map((w) => (
                <option key={w._id} value={w._id}>
                  {w.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-stone-600 mb-1">{t('admin.excelFile')}</label>
            <input
              ref={fileInputRef}
              type="file"
              accept=".xlsx,.xls,.ods,.csv"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) importMutation.mutate(file)
              }}
              className="text-sm"
            />
          </div>
          {importMutation.isPending && (
            <span className="text-sm text-stone-500">{t('admin.importing')}</span>
          )}
        </div>
        {importMessage && <p className="text-sm text-stone-700">{importMessage}</p>}
      </div>

      <AdminListSearchBar
        value={searchInput}
        onChange={setSearchInput}
        placeholder={t('admin.searchBooksPlaceholder')}
        hint={t('admin.listAutoSearchHint')}
        isFetching={isFetching}
        committedValue={committedSearch}
        onCommit={commitSearch}
        className="mb-4"
      />

      <div className="flex flex-wrap gap-3 mb-6">
        <select
          value={conditionFilter}
          onChange={(e) => setConditionFilter(e.target.value)}
          className="px-3 py-2 border border-stone-300 rounded-lg text-sm"
        >
          <option value="">{t('admin.allConditions')}</option>
          <option value="new">{t('admin.conditionNew')}</option>
          <option value="used">{t('admin.conditionUsed')}</option>
        </select>
        <select
          value={visibilityFilter}
          onChange={(e) => setVisibilityFilter(e.target.value)}
          className="px-3 py-2 border border-stone-300 rounded-lg text-sm"
        >
          <option value="">{t('admin.allVisibility')}</option>
          <option value="visible">{t('admin.visible')}</option>
          <option value="hidden">{t('admin.hidden')}</option>
          <option value="sold">{t('admin.sold')}</option>
        </select>
      </div>

      {queryErrorMessage && (
        <p className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
          {queryErrorMessage}
        </p>
      )}
      {items.length > 0 && (
        <div className="flex flex-wrap items-center gap-3 mb-3">
          <button
            type="button"
            onClick={handleBulkDelete}
            disabled={selectedCount === 0 || deletingBulk || deleteMutation.isPending}
            className="px-4 py-2 rounded-lg border border-red-300 text-red-700 bg-red-50 hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
          >
            {deletingBulk ? t('admin.deleting') : t('admin.deleteSelected')}
            {selectedCount > 0 ? ` (${selectedCount})` : ''}
          </button>
        </div>
      )}
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-2 py-2 w-10 text-center">
                <input
                  ref={headerCheckboxRef}
                  type="checkbox"
                  checked={allSelected}
                  onChange={toggleSelectAllOnPage}
                  aria-label={t('admin.selectAll')}
                />
              </th>
              <th className="px-4 py-2 text-left">{t('admin.title')}</th>
              <th className="px-4 py-2 text-left">{t('admin.store')}</th>
              <th className="px-4 py-2 text-left">{t('admin.condition')}</th>
              <th className="px-4 py-2 text-left">{t('admin.price')}</th>
              <th className="px-4 py-2 text-left">{t('admin.stock')}</th>
              <th className="px-4 py-2 text-left">{t('admin.status')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((book: Book) => (
              <tr key={book._id} className="border-t border-stone-200">
                <td className="px-2 py-2 text-center">
                  <input
                    type="checkbox"
                    checked={selectedIds.has(book._id)}
                    onChange={() => toggleRow(book._id)}
                    aria-label={book.title}
                  />
                </td>
                <td className="px-4 py-2">{book.title}</td>
                <td className="px-4 py-2">{book.warehouse?.name ?? '-'}</td>
                <td className="px-4 py-2">
                  {book.condition === 'used' ? t('admin.conditionUsed') : t('admin.conditionNew')}
                </td>
                <td className="px-4 py-2">${Number(book.price ?? 0).toFixed(2)}</td>
                <td className="px-4 py-2">{book.stock_quantity ?? 0}</td>
                <td className="px-4 py-2">
                  {book.is_sold
                    ? t('admin.sold')
                    : book.is_visible === false
                      ? t('admin.hidden')
                      : t('admin.visible')}
                </td>
                <td className="px-4 py-2 text-right">
                  <Link
                    to={`/admin/books/${book._id}/edit`}
                    className="text-amber-700 hover:underline mr-3"
                  >
                    {t('admin.edit')}
                  </Link>
                  <button
                    type="button"
                    onClick={() => handleDelete(book)}
                    disabled={deleteMutation.isPending && deleteId === book._id}
                    className="text-red-600 hover:underline disabled:opacity-50"
                  >
                    {deleteMutation.isPending && deleteId === book._id
                      ? t('admin.deleting')
                      : t('admin.delete')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-8">
          {t('admin.noBooks')}{' '}
          <Link to="/admin/books/new" className="text-amber-700 font-medium">
            {t('admin.addFirstBook')}
          </Link>
        </p>
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
