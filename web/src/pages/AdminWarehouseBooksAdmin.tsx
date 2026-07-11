import { useEffect, useRef, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'
import type { Book, Warehouse } from '../lib/api'

export function AdminWarehouseBooksAdmin() {
  const { id } = useParams<{ id: string }>()
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [deleteId, setDeleteId] = useState<string | null>(null)
  const headerCheckboxRef = useRef<HTMLInputElement>(null)
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set())

  const { data: whData } = useQuery({
    queryKey: ['admin-warehouse', id],
    queryFn: async () => {
      const res = await admin.warehouses.get(id!)
      return res.data
    },
    enabled: !!id,
  })

  const warehouse: Warehouse | undefined = whData?.data

  const { data, isLoading, isFetching, error } = useQuery({
    queryKey: ['admin-books-warehouse', id, page],
    queryFn: async () => {
      const res = await admin.books.list({
        page,
        per_page: 32,
        warehouse_id: id!,
      })
      return res.data
    },
    enabled: !!id,
  })

  const deleteMutation = useMutation({
    mutationFn: (bookId: string) => admin.books.delete(bookId),
    onSuccess: (_, bookId) => {
      queryClient.invalidateQueries({ queryKey: ['admin-books-warehouse'] })
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      setDeleteId(null)
      setSelectedIds((prev) => {
        const next = new Set(prev)
        next.delete(bookId)
        return next
      })
    },
  })

  const bulkDeleteMutation = useMutation({
    mutationFn: async (ids: string[]) => {
      await Promise.all(ids.map((bookId) => admin.books.delete(bookId)))
    },
    onSuccess: (_, ids) => {
      queryClient.invalidateQueries({ queryKey: ['admin-books-warehouse'] })
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      setSelectedIds((prev) => {
        const next = new Set(prev)
        ids.forEach((x) => next.delete(x))
        return next
      })
    },
  })

  const queryErrorMessage =
    (error as { response?: { data?: { message?: string } } } | null)?.response?.data?.message ?? undefined

  const paginated = data?.data
  const items: Book[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null
  const pageIds = items.map((b) => b._id)
  const allSelected = pageIds.length > 0 && pageIds.every((x) => selectedIds.has(x))
  const someSelected = pageIds.some((x) => selectedIds.has(x))

  useEffect(() => {
    const el = headerCheckboxRef.current
    if (el) el.indeterminate = someSelected && !allSelected
  }, [someSelected, allSelected])

  if (isLoading && !data) {
    return <div className="text-center py-12">{t('common.loading')}</div>
  }

  const toggleRow = (bookId: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(bookId)) next.delete(bookId)
      else next.add(bookId)
      return next
    })
  }

  const toggleSelectAllOnPage = () => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (allSelected) pageIds.forEach((x) => next.delete(x))
      else pageIds.forEach((x) => next.add(x))
      return next
    })
  }

  const handleDelete = (book: Book) => {
    if (window.confirm(t('admin.deleteBookConfirm', { title: book.title }))) {
      setDeleteId(book._id)
      deleteMutation.mutate(book._id)
    }
  }

  const handleBulkDelete = () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return
    if (window.confirm(t('admin.deleteBooksBulkConfirm', { count: ids.length }))) {
      bulkDeleteMutation.mutate(ids)
    }
  }

  return (
    <div>
      <div className="mb-4">
        <Link to="/admin/warehouse-books" className="text-amber-700 hover:underline text-sm">
          ← {t('admin.backToWarehouses')}
        </Link>
      </div>
      <h1 className="text-2xl font-bold text-amber-900 mb-1">
        {warehouse?.name ?? t('admin.warehouseBooksTitle')}
      </h1>
      {warehouse && (
        <p className="text-stone-600 mb-4">{[warehouse.city, warehouse.country].filter(Boolean).join(', ')}</p>
      )}
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
            disabled={
              selectedIds.size === 0 || bulkDeleteMutation.isPending || deleteMutation.isPending
            }
            className="px-4 py-2 rounded-lg border border-red-300 text-red-700 bg-red-50 hover:bg-red-100 disabled:opacity-50 text-sm font-medium"
          >
            {bulkDeleteMutation.isPending ? t('admin.deleting') : t('admin.deleteSelected')}
            {selectedIds.size > 0 ? ` (${selectedIds.size})` : ''}
          </button>
          {isFetching && <span className="text-sm text-stone-500">{t('common.loading')}</span>}
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
              <th className="px-4 py-2 text-left">{t('admin.isbn')}</th>
              <th className="px-4 py-2 text-left">{t('admin.publisher') ?? 'Publisher'}</th>
              <th className="px-4 py-2 text-left">{t('admin.price')}</th>
              <th className="px-4 py-2 text-left">{t('admin.stock')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((book) => (
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
                <td className="px-4 py-2">{book.isbn ?? '-'}</td>
                <td className="px-4 py-2">{typeof book.publisher === 'string' ? book.publisher : (book.publisher?.name ?? '-')}</td>
                <td className="px-4 py-2">${Number(book.price ?? 0).toFixed(2)}</td>
                <td className="px-4 py-2">{book.stock_quantity ?? 0}</td>
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
        <p className="text-center text-stone-500 py-8">{t('admin.noBooks')}</p>
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
