import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'
import type { Book } from '../lib/api'

const PER_PAGE = 50

export function AdminReportsBooksWithoutCover() {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['admin-books-without-cover', page],
    queryFn: async () => {
      const res = await admin.books.list({ page, per_page: PER_PAGE, no_cover: 1 })
      return res.data
    },
  })

  if (isLoading) return <div className="text-center py-12">{t('common.loading')}</div>

  const paginated = data?.data
  const items: Book[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

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
      <p className="text-stone-600 mb-6">
        {t('admin.reports.booksWithoutCoverHint') ??
          'These books are hidden from the public catalog. Add a cover image to show them on the site.'}
      </p>
      {items.length === 0 ? (
        <p className="text-center text-stone-500 py-8 bg-white rounded-lg border border-stone-200">
          {t('admin.reports.noBooksWithoutCover') ?? 'All books have a cover image.'}
        </p>
      ) : (
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
                      className="text-amber-700 hover:underline"
                    >
                      {t('admin.edit')}
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
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
