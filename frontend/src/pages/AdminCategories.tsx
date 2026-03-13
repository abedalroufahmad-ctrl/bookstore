import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Category } from '../lib/api'
import { Pagination } from '../components/Pagination'

export function AdminCategories() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ dewey_code: '', subject_title: '' })
  const [error, setError] = useState('')
  const [editingId, setEditingId] = useState<string | null>(null)
  const [editingForm, setEditingForm] = useState({ dewey_code: '', subject_title: '' })
  const [booksCounts, setBooksCounts] = useState<Record<string, number>>({})

  const { data } = useQuery({
    queryKey: ['admin-categories', page],
    queryFn: async () => {
      const res = await admin.categories.list({ page, per_page: 32 })
      return res.data
    },
  })

  const createMutation = useMutation({
    mutationFn: (d: { dewey_code: string; subject_title: string }) =>
      admin.categories.create(d),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
      setForm({ dewey_code: '', subject_title: '' })
      setShowForm(false)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.categories.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data: d }: { id: string; data: { dewey_code: string; subject_title: string } }) =>
      admin.categories.update(id, d),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
      setEditingId(null)
      setEditingForm({ dewey_code: '', subject_title: '' })
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const paginated = data?.data
  const items: Category[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  useEffect(() => {
    let cancelled = false
    const loadCounts = async () => {
      if (!items.length) return
      try {
        const entries = await Promise.all(
          items.map(async (c) => {
            try {
              const res = await admin.books.list({ category_id: c._id, per_page: 1 })
              const paginatedBooks = res.data.data
              const total =
                paginatedBooks && typeof (paginatedBooks as any).total === 'number'
                  ? (paginatedBooks as any).total
                  : Array.isArray((paginatedBooks as any)?.data)
                    ? (paginatedBooks as any).data.length
                    : 0
              return [c._id, total] as const
            } catch {
              return [c._id, 0] as const
            }
          })
        )
        if (cancelled) return
        setBooksCounts((prev) => {
          const next = { ...prev }
          for (const [id, total] of entries) {
            next[id] = total
          }
          return next
        })
      } catch {
        // ignore errors; counts just won't show
      }
    }
    loadCounts()
    return () => {
      cancelled = true
    }
  }, [items])

  const handleStartEdit = (c: Category) => {
    setEditingId(c._id)
    setEditingForm({ dewey_code: c.dewey_code, subject_title: c.subject_title })
    setError('')
  }

  const handleSaveEdit = () => {
    if (!editingId || !editingForm.dewey_code.trim() || !editingForm.subject_title.trim()) return
    updateMutation.mutate({ id: editingId, data: editingForm })
  }

  const handleCancelEdit = () => {
    setEditingId(null)
    setEditingForm({ dewey_code: '', subject_title: '' })
    setError('')
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.categories')}</h1>
        <button
          type="button"
          onClick={() => setShowForm(true)}
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          {t('admin.addCategory')}
        </button>
      </div>
      {showForm && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-2">{t('admin.newCategory')}</h2>
          <div className="flex gap-2 flex-wrap">
            <input
              type="text"
              value={form.dewey_code}
              onChange={(e) => setForm((p) => ({ ...p, dewey_code: e.target.value }))}
              placeholder={t('admin.deweyCode')}
              className="w-28 px-4 py-2 border border-stone-300 rounded-lg"
            />
            <input
              type="text"
              value={form.subject_title}
              onChange={(e) => setForm((p) => ({ ...p, subject_title: e.target.value }))}
              placeholder={t('admin.subjectTitle')}
              className="flex-1 min-w-[200px] px-4 py-2 border border-stone-300 rounded-lg"
            />
            <button
              type="button"
              onClick={() =>
                form.dewey_code &&
                form.subject_title &&
                createMutation.mutate(form)
              }
              disabled={
                createMutation.isPending ||
                !form.dewey_code ||
                !form.subject_title
              }
              className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
            >
              {t('admin.create')}
            </button>
            <button
              type="button"
              onClick={() => {
                setShowForm(false)
                setForm({ dewey_code: '', subject_title: '' })
                setError('')
              }}
              className="px-4 py-2 border border-stone-300 rounded-lg"
            >
              {t('admin.cancel')}
            </button>
          </div>
          {error && <p className="mt-2 text-red-600 text-sm">{error}</p>}
        </div>
      )}
      <div className="mb-4">{error && editingId && <p className="text-red-600 text-sm">{error}</p>}</div>
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">{t('admin.deweyCode')}</th>
              <th className="px-4 py-2 text-left">{t('admin.subject')}</th>
              <th className="px-4 py-2 text-center">{t('admin.booksCount')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((c) => (
              <tr key={c._id} className="border-t border-stone-200">
                <td className="px-4 py-2">
                  {editingId === c._id ? (
                    <input
                      type="text"
                      value={editingForm.dewey_code}
                      onChange={(e) => setEditingForm((p) => ({ ...p, dewey_code: e.target.value }))}
                      placeholder={t('admin.deweyCode')}
                      className="w-24 px-3 py-1.5 border border-stone-300 rounded-lg"
                    />
                  ) : (
                    c.dewey_code
                  )}
                </td>
                <td className="px-4 py-2">
                  {editingId === c._id ? (
                    <input
                      type="text"
                      value={editingForm.subject_title}
                      onChange={(e) => setEditingForm((p) => ({ ...p, subject_title: e.target.value }))}
                      placeholder={t('admin.subjectTitle')}
                      className="w-full min-w-[150px] px-3 py-1.5 border border-stone-300 rounded-lg"
                    />
                  ) : (
                    c.subject_title
                  )}
                </td>
                <td className="px-4 py-2 text-center text-sm text-stone-700">
                  {booksCounts[c._id] ?? '—'}
                </td>
                <td className="px-4 py-2 text-right">
                  {editingId === c._id ? (
                    <>
                      <button
                        type="button"
                        onClick={handleSaveEdit}
                        disabled={
                          updateMutation.isPending ||
                          !editingForm.dewey_code.trim() ||
                          !editingForm.subject_title.trim()
                        }
                        className="text-amber-700 hover:underline text-sm mr-3 disabled:opacity-50"
                      >
                        {updateMutation.isPending ? t('common.saving') : t('admin.update')}
                      </button>
                      <button
                        type="button"
                        onClick={handleCancelEdit}
                        className="text-stone-500 hover:underline text-sm"
                      >
                        {t('admin.cancel')}
                      </button>
                    </>
                  ) : (
                    <>
                      <button
                        type="button"
                        onClick={() => handleStartEdit(c)}
                        className="text-amber-700 hover:underline text-sm mr-3"
                      >
                        {t('admin.edit')}
                      </button>
                      <button
                        type="button"
                        onClick={() =>
                          window.confirm(t('admin.deleteCategoryConfirm')) &&
                          deleteMutation.mutate(c._id)
                        }
                        className="text-red-600 hover:underline text-sm"
                      >
                        {t('admin.delete')}
                      </button>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 && !showForm && (
        <p className="text-center text-stone-500 py-8">{t('admin.noCategories')}</p>
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
