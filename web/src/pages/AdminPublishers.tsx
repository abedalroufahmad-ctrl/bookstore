import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { admin, type Publisher, type Warehouse, type WarehouseFormData } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { AdminListSearchBar } from '../components/AdminListSearchBar'
import { useSearchCommit } from '../hooks/useSearchCommit'

const emptyWarehouseForm: WarehouseFormData = {
  name: '',
  address: '',
  country: '',
  city: '',
  phone: '',
  email: '',
  publisher_id: '',
}

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
  const [editingWarehouses, setEditingWarehouses] = useState<Warehouse[]>([])
  const [warehouseForm, setWarehouseForm] = useState<WarehouseFormData>(emptyWarehouseForm)
  const [warehouseError, setWarehouseError] = useState('')

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
      if (editingId) {
        queryClient.invalidateQueries({ queryKey: ['admin-publisher', editingId] })
      }
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const createWarehouseMutation = useMutation({
    mutationFn: (d: WarehouseFormData) => admin.warehouses.create(d),
    onSuccess: async () => {
      queryClient.invalidateQueries({ queryKey: ['admin-warehouses'] })
      queryClient.invalidateQueries({ queryKey: ['admin-publishers'] })
      if (editingId) {
        const res = await admin.publishers.get(editingId)
        setEditingWarehouses(res.data.data.warehouses ?? [])
      }
      setWarehouseForm({ ...emptyWarehouseForm, publisher_id: editingId ?? '' })
      setWarehouseError('')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setWarehouseError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  const paginated = data?.data
  const items: Publisher[] = paginated?.data ?? []
  const meta = paginated && 'current_page' in paginated ? paginated : null

  const isValidWarehouseForm = (f: WarehouseFormData) =>
    f.name.trim() && f.address.trim() && f.country.trim() && f.city.trim() && f.email.trim() && f.publisher_id.trim()

  const handleStartEdit = async (p: Publisher) => {
    setEditingId(p._id)
    setEditingForm({ name: p.name, address: p.address || '', website: p.website || '' })
    setWarehouseForm({ ...emptyWarehouseForm, publisher_id: p._id })
    setError('')
    setWarehouseError('')
    try {
      const res = await admin.publishers.get(p._id)
      const full = res.data.data
      setEditingForm({
        name: full.name,
        address: full.address || '',
        website: full.website || '',
      })
      setEditingWarehouses(full.warehouses ?? [])
    } catch {
      setEditingWarehouses([])
    }
  }

  const handleSaveEdit = () => {
    if (!editingId || !editingForm.name.trim()) return
    updateMutation.mutate({ id: editingId, data: editingForm })
  }

  const handleCancelEdit = () => {
    setEditingId(null)
    setEditingForm({ name: '', address: '', website: '' })
    setEditingWarehouses([])
    setWarehouseForm(emptyWarehouseForm)
    setError('')
    setWarehouseError('')
  }

  const handleAddWarehouse = () => {
    if (!isValidWarehouseForm(warehouseForm)) {
      setWarehouseError(t('admin.fillRequired'))
      return
    }
    createWarehouseMutation.mutate(warehouseForm)
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
      {editingId && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.edit')} — {editingForm.name}</h2>
          <div className="grid gap-4 max-w-2xl mb-6">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.publisherName')}</label>
                <input
                  type="text"
                  value={editingForm.name}
                  onChange={(e) => setEditingForm((prev) => ({ ...prev, name: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.publisherAddress')}</label>
                <input
                  type="text"
                  value={editingForm.address}
                  onChange={(e) => setEditingForm((prev) => ({ ...prev, address: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
            </div>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={handleSaveEdit}
                disabled={updateMutation.isPending || !editingForm.name.trim()}
                className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
              >
                {updateMutation.isPending ? t('common.saving') : t('admin.update')}
              </button>
              <button type="button" onClick={handleCancelEdit} className="px-4 py-2 border border-stone-300 rounded-lg">
                {t('admin.cancel')}
              </button>
              <Link 
                to={`/admin/publishers/${editingId}/settings`} 
                className="px-4 py-2 bg-stone-100 text-stone-700 border border-stone-300 rounded-lg hover:bg-stone-200 ml-auto"
              >
                ⚙️ {t('admin.publisherSettings', 'Settings')}
              </Link>
            </div>
            {error && <p className="text-red-600 text-sm">{error}</p>}
          </div>

          <h3 className="font-semibold mb-2">{t('admin.publisherWarehouses')}</h3>
          {editingWarehouses.length > 0 ? (
            <ul className="mb-4 list-disc list-inside text-stone-700">
              {editingWarehouses.map((w) => (
                <li key={w._id}>
                  {w.name} — {w.city}, {w.country}
                </li>
              ))}
            </ul>
          ) : (
            <p className="mb-4 text-stone-500 text-sm">{t('admin.noWarehousesList')}</p>
          )}

          <h3 className="font-semibold mb-2">{t('admin.addWarehouseForPublisher')}</h3>
          <div className="grid gap-4 max-w-2xl">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.name')}</label>
                <input
                  type="text"
                  value={warehouseForm.name}
                  onChange={(e) => setWarehouseForm((p) => ({ ...p, name: e.target.value }))}
                  placeholder={t('admin.warehouseName')}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.email')}</label>
                <input
                  type="email"
                  value={warehouseForm.email}
                  onChange={(e) => setWarehouseForm((p) => ({ ...p, email: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.address')}</label>
              <input
                type="text"
                value={warehouseForm.address}
                onChange={(e) => setWarehouseForm((p) => ({ ...p, address: e.target.value }))}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.city')}</label>
                <input
                  type="text"
                  value={warehouseForm.city}
                  onChange={(e) => setWarehouseForm((p) => ({ ...p, city: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.country')}</label>
                <input
                  type="text"
                  value={warehouseForm.country}
                  onChange={(e) => setWarehouseForm((p) => ({ ...p, country: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.phone')} ({t('admin.optional')})</label>
              <input
                type="text"
                value={warehouseForm.phone ?? ''}
                onChange={(e) => setWarehouseForm((p) => ({ ...p, phone: e.target.value }))}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <button
              type="button"
              onClick={handleAddWarehouse}
              disabled={createWarehouseMutation.isPending || !isValidWarehouseForm(warehouseForm)}
              className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50 self-start"
            >
              {createWarehouseMutation.isPending ? t('common.saving') : t('admin.addWarehouse')}
            </button>
            {warehouseError && <p className="text-red-600 text-sm">{warehouseError}</p>}
          </div>
        </div>
      )}
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">{t('admin.publisherName') ?? 'Name'}</th>
              <th className="px-4 py-2 text-left">{t('admin.publisherAddress') ?? 'Address'}</th>
              <th className="px-4 py-2 text-left">{t('admin.warehousesCount')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((p) => (
              <tr key={p._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{p.name}</td>
                <td className="px-4 py-2">{p.address || '—'}</td>
                <td className="px-4 py-2">{p.warehouses_count ?? 0}</td>
                <td className="px-4 py-2 text-right">
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
