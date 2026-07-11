import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Publisher } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { AdminListSearchBar } from '../components/AdminListSearchBar'
import { useSearchCommit } from '../hooks/useSearchCommit'

export function AdminPublishers() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ name: '', address: '', website: '' })
  const [error, setError] = useState('')
  const [editingId, setEditingId] = useState<string | null>(null)
  const [editingForm, setEditingForm] = useState({ name: '', address: '', website: '' })

  useEffect(() => {
    setPage(1)
  }, [committedSearch])

  const { data, isFetching } = useQuery({
    queryKey: ['admin-publishers', page, committedSearch],
    queryFn: async () => {
      const res = await admin.publishers.list({
        page,
        per_page: 32,
        ...(committedSearch ? { search: committedSearch } : {}),
      })
      return res.data
    },
  })

  const createMutation = useMutation({
    mutationFn: (d: { name: string; address?: string; website?: string }) =>
      admin.publishers.create(d),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-publishers'] })
      setForm({ name: '', address: '', website: '' })
      setShowForm(false)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.publishers.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-publishers'] })
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data: d }: { id: string; data: { name: string; address?: string; website?: string } }) =>
      admin.publishers.update(id, d),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-publishers'] })
      setEditingId(null)
      setEditingForm({ name: '', address: '', website: '' })
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const paginated = data?.data
  const items: Publisher[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  const handleStartEdit = (p: Publisher) => {
    setEditingId(p._id)
    setEditingForm({ name: p.name, address: p.address || '', website: p.website || '' })
    setError('')
  }

  const handleSaveEdit = () => {
    if (!editingId || !editingForm.name.trim()) return
    updateMutation.mutate({ id: editingId, data: editingForm })
  }

  const handleCancelEdit = () => {
    setEditingId(null)
    setEditingForm({ name: '', address: '', website: '' })
    setError('')
  }

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.publishers') ?? 'Publishers'}</h1>
        <button
          type="button"
          onClick={() => setShowForm(true)}
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 shrink-0 self-start"
        >
          {t('admin.addPublisher') ?? 'Add Publisher'}
        </button>
      </div>
      <AdminListSearchBar
        value={searchInput}
        onChange={setSearchInput}
        placeholder={t('admin.searchPublishersPlaceholder') ?? 'Search publishers...'}
        hint={t('admin.listAutoSearchHint')}
        isFetching={isFetching}
        committedValue={committedSearch}
        onCommit={commitSearch}
        className="mb-6"
      />
      {showForm && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-2">{t('admin.newPublisher') ?? 'New Publisher'}</h2>
          <div className="flex gap-2 flex-wrap">
            <input
              type="text"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              placeholder={t('admin.publisherName') ?? 'Name'}
              className="flex-1 min-w-[200px] px-4 py-2 border border-stone-300 rounded-lg"
            />
            <input
              type="text"
              value={form.address}
              onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))}
              placeholder={t('admin.publisherAddress') ?? 'Address'}
              className="flex-1 min-w-[200px] px-4 py-2 border border-stone-300 rounded-lg"
            />
            <button
              type="button"
              onClick={() =>
                form.name &&
                createMutation.mutate(form)
              }
              disabled={
                createMutation.isPending ||
                !form.name
              }
              className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
            >
              {t('admin.create')}
            </button>
            <button
              type="button"
              onClick={() => {
                setShowForm(false)
                setForm({ name: '', address: '', website: '' })
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
              <th className="px-4 py-2 text-left">{t('admin.publisherName') ?? 'Name'}</th>
              <th className="px-4 py-2 text-left">{t('admin.publisherAddress') ?? 'Address'}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((p) => (
              <tr key={p._id} className="border-t border-stone-200">
                <td className="px-4 py-2">
                  {editingId === p._id ? (
                    <input
                      type="text"
                      value={editingForm.name}
                      onChange={(e) => setEditingForm((prev) => ({ ...prev, name: e.target.value }))}
                      placeholder={t('admin.publisherName') ?? 'Name'}
                      className="w-full px-3 py-1.5 border border-stone-300 rounded-lg"
                    />
                  ) : (
                    p.name
                  )}
                </td>
                <td className="px-4 py-2">
                  {editingId === p._id ? (
                    <input
                      type="text"
                      value={editingForm.address}
                      onChange={(e) => setEditingForm((prev) => ({ ...prev, address: e.target.value }))}
                      placeholder={t('admin.publisherAddress') ?? 'Address'}
                      className="w-full px-3 py-1.5 border border-stone-300 rounded-lg"
                    />
                  ) : (
                    p.address || '—'
                  )}
                </td>
                <td className="px-4 py-2 text-right">
                  {editingId === p._id ? (
                    <>
                      <button
                        type="button"
                        onClick={handleSaveEdit}
                        disabled={
                          updateMutation.isPending ||
                          !editingForm.name.trim()
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
                        onClick={() => handleStartEdit(p)}
                        className="text-amber-700 hover:underline text-sm mr-3"
                      >
                        {t('admin.edit')}
                      </button>
                      <button
                        type="button"
                        onClick={() =>
                          window.confirm(t('admin.deletePublisherConfirm') ?? 'Are you sure you want to delete this publisher?') &&
                          deleteMutation.mutate(p._id)
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
        <p className="text-center text-stone-500 py-8">{t('admin.noPublishers') ?? 'No publishers found.'}</p>
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
