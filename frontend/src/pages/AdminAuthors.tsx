import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { admin, type Author } from '../lib/api'
import { resolveCoverUrl } from '../lib/utils'
import { Pagination } from '../components/Pagination'

function formatDate(s: string | undefined): string {
  if (!s) return '-'
  try {
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toLocaleDateString()
  } catch {
    return s
  }
}

export function AdminAuthors() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)

  const { data } = useQuery({
    queryKey: ['admin-authors', page],
    queryFn: async () => {
      const res = await admin.authors.list({ page, per_page: 32 })
      return res.data
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.authors.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-authors'] })
    },
  })

  const paginated = data?.data
  const items: Author[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.authors')}</h1>
        <Link
          to="/admin/authors/new"
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          {t('admin.addAuthor')}
        </Link>
      </div>
      <div className="bg-white rounded-lg border border-stone-200 overflow-x-auto">
        <table className="w-full min-w-[700px]">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">{t('admin.photo')}</th>
              <th className="px-4 py-2 text-left">{t('admin.name')}</th>
              <th className="px-4 py-2 text-left">{t('admin.biography')}</th>
              <th className="px-4 py-2 text-left">{t('admin.dateOfBirth')}</th>
              <th className="px-4 py-2 text-left">{t('admin.dateOfDeath')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((a) => (
              <tr key={a._id} className="border-t border-stone-200">
                <td className="px-4 py-2">
                  {a.photo ? (
                    <img
                      src={resolveCoverUrl(a.photo)}
                      alt={a.name}
                      className="w-10 h-10 rounded-full object-cover"
                    />
                  ) : (
                    <div className="w-10 h-10 rounded-full bg-stone-200 flex items-center justify-center text-stone-500 text-sm">
                      {a.name?.charAt(0) || '?'}
                    </div>
                  )}
                </td>
                <td className="px-4 py-2 font-medium">{a.name}</td>
                <td className="px-4 py-2 max-w-[200px]">
                  <span className="text-sm text-stone-600 line-clamp-2">
                    {a.biography || '-'}
                  </span>
                </td>
                <td className="px-4 py-2 text-sm">{formatDate(a.date_of_birth)}</td>
                <td className="px-4 py-2 text-sm">{formatDate(a.date_of_death)}</td>
                <td className="px-4 py-2 text-right">
                  <Link
                    to={`/admin/authors/${a._id}/edit`}
                    className="text-amber-700 hover:underline text-sm mr-3"
                  >
                    {t('admin.edit')}
                  </Link>
                  <button
                    type="button"
                    onClick={() =>
                      window.confirm(t('admin.deleteAuthorConfirm')) && deleteMutation.mutate(a._id)
                    }
                    className="text-red-600 hover:underline text-sm"
                  >
                    {t('admin.delete')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-8">{t('admin.noAuthors')}</p>
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
