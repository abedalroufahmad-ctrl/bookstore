import { useEffect, useRef, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { AdminListSearchBar } from '../components/AdminListSearchBar'
import { useSearchCommit } from '../hooks/useSearchCommit'
import type { Book } from '../lib/api'

const PER_PAGE = 50

export function AdminReportsBooksWithoutCover() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [deleteId, setDeleteId] = useState<string | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set())
  const headerCheckboxRef = useRef<HTMLInputElement>(null)
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()

  useEffect(() => {
    setPage(1)
  }, [committedSearch])

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['admin-books-without-cover', page, committedSearch],
    queryFn: async () => {
      const res = await admin.books.list({
        page,
        per_page: PER_PAGE,
        no_cover: 1,
        ...(committedSearch ? { search: committedSearch } : {}),
      })
      return res.data
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.books.delete(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['admin-books-without-cover'] })
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
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
      queryClient.invalidateQueries({ queryKey: ['admin-books-without-cover'] })
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      setSelectedIds((prev) => {
        const next = new Set(prev)
        ids.forEach((id) => next.delete(id))
        return next
      })
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
        }),
      )
    ) {
      bulkDeleteMutation.mutate(ids)
    }
  }

  if (isLoading && !data) return <div className="text-center py-12">{t('common.loading')}</div>

  const paginated = data?.data
  const items: Book[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  const pageIds = items.map((b) => b._id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id))
  const someSelected = pageIds.some((id) => selectedIds.has(id))

  useEffect(() => {
    const el = headerCheckboxRef.current
    if (el) {
      el.indeterminate = someSelected && !allSelected
    }
  }, [someSelected, allSelected])

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
      <div className="flex items-center gap-4 mb-6">
        <Link to="/admin" className="text-amber-700 hover:underline text-sm">
          ← {t('admin.dashboard')}
        </Link>
      </div>
      <h1 className="text-2xl font-bold text-amber-900 mb-2">
        {t('admin.reports.booksWithoutCover') ?? 'Books without cover image'}
      </h1>
      <p className="text-stone-600 mb-4">
        {t('admin.reports.booksWithoutCoverHint') ??
          'These books are hidden from the public catalog. Add a cover image to show them on the site.'}
      </p>
      <AdminListSearchBar
        value={searchInput}
        onChange={setSearchInput}
        placeholder={t('admin.searchReportsBooksPlaceholder')}
        hint={t('admin.listAutoSearchHint')}
        isFetching={isFetching}
        committedValue={committedSearch}
        onCommit={commitSearch}
        className="mb-6"
      />
      {items.length === 0 ? (
        <p className="text-center text-stone-500 py-8 bg-white rounded-lg border border-stone-200">
          {t('admin.reports.noBooksWithoutCover') ?? 'All books have a cover image.'}
        </p>
      ) : (
        <>
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
                  <th className="px-4 py-2 text-left">{t('admin.price')}</th>
                  <th className="px-4 py-2 text-left">{t('admin.stock')}</th>
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
                    <td className="px-4 py-2">{book.isbn ?? '-'}</td>
                    <td className="px-4 py-2">${book.price?.toFixed(2)}</td>
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
        </>
      )}
      {meta && meta.last_page > 1 && (
        <div className="mt-4">
          <Pagination
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            perPage={meta.per_page}
            onPageChange={setPage}
          />
        </div>
      )}
    </div>
  )
}
