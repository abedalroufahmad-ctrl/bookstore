import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'
import type { Book } from '../lib/api'

export function AdminBooks() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [deleteId, setDeleteId] = useState<string | null>(null)
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['admin-books', page],
    queryFn: async () => {
      const res = await admin.books.list({ page, per_page: 32 })
      return res.data
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.books.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      setDeleteId(null)
    },
  })

  const handleDelete = (book: Book) => {
    if (window.confirm(t('admin.deleteBookConfirm', { title: book.title }))) {
      deleteMutation.mutate(book._id)
      setDeleteId(book._id)
    }
  }

  if (isLoading) return <div className="text-center py-12">{t('common.loading')}</div>

  const paginated = data?.data
  const items = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.books')}</h1>
        <Link
          to="/admin/books/new"
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          {t('admin.addBook')}
        </Link>
      </div>
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
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
