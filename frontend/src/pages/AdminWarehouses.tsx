import { useEffect, useMemo, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Warehouse, type WarehouseFormData } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { AdminListSearchBar } from '../components/AdminListSearchBar'
import { useSearchCommit } from '../hooks/useSearchCommit'
import { useAuth } from '../contexts/AuthContext'

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return []
}

const emptyForm: WarehouseFormData = {
  name: '',
  address: '',
  country: '',
  city: '',
  phone: '',
  email: '',
  manager_id: null,
  employee_ids: [],
}

export function AdminWarehouses() {
  const { t } = useTranslation()
  const { user, userType } = useAuth()
  const isWarehouseManagerUser =
    userType === 'employee' && (user as { role?: string } | null)?.role === 'warehouse_manager'
  const queryClient = useQueryClient()
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState<WarehouseFormData>(emptyForm)
  const [error, setError] = useState('')
  const [editingId, setEditingId] = useState<string | null>(null)
  const [editingForm, setEditingForm] = useState<WarehouseFormData>(emptyForm)
  const [page, setPage] = useState(1)

  useEffect(() => {
    setPage(1)
  }, [committedSearch])

  const { data, isFetching } = useQuery({
    queryKey: ['admin-warehouses', page, isWarehouseManagerUser ? 'wm' : committedSearch],
    queryFn: async () => {
      const res = await admin.warehouses.list({
        page,
        per_page: 15,
        ...(!isWarehouseManagerUser && committedSearch ? { search: committedSearch } : {}),
      })
      return res.data
    },
  })

  const { data: employeesData } = useQuery({
    queryKey: ['admin-employees-all'],
    queryFn: async () => {
      const res = await admin.employees.list({ per_page: 500 })
      return res.data
    },
  })
  const employees = extractList<{ _id: string; name: string; email: string }>(employeesData)

  const createMutation = useMutation({
    mutationFn: (d: WarehouseFormData) => admin.warehouses.create(d),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-warehouses'] })
      setForm(emptyForm)
      setShowForm(false)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.warehouses.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-warehouses'] })
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data: d }: { id: string; data: WarehouseFormData }) =>
      admin.warehouses.update(id, d),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-warehouses'] })
      setEditingId(null)
      setEditingForm(emptyForm)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const paginated = data?.data
  const rawItems: Warehouse[] = paginated?.data ?? []
  const items = useMemo(() => {
    if (!isWarehouseManagerUser || !committedSearch) return rawItems
    const s = committedSearch.toLowerCase()
    return rawItems.filter((w) =>
      [w.name, w.email, w.city, w.country, w.address, w.phone]
        .filter(Boolean)
        .some((field) => String(field).toLowerCase().includes(s))
    )
  }, [rawItems, isWarehouseManagerUser, committedSearch])
  const meta = paginated && 'current_page' in paginated ? paginated : null

  const handleStartEdit = async (w: Warehouse) => {
    setError('')
    try {
      const res = await admin.warehouses.get(w._id)
      const full = res.data.data
      setEditingId(full._id)
      setEditingForm({
        name: full.name,
        address: full.address ?? '',
        country: full.country ?? '',
        city: full.city ?? '',
        phone: full.phone ?? '',
        email: full.email ?? '',
        manager_id: full.manager_id ?? null,
        employee_ids: (full.employees ?? []).map((e) => e._id),
      })
    } catch {
      setError(t('admin.failedUpdate'))
    }
  }

  const isValidForm = (f: WarehouseFormData) =>
    f.name.trim() && f.address.trim() && f.country.trim() && f.city.trim() && f.email.trim()

  const handleSaveEdit = () => {
    if (!editingId || !isValidForm(editingForm)) return
    const payload = { ...editingForm }
    if (payload.manager_id === '' || payload.manager_id === undefined) payload.manager_id = null
    if (!payload.employee_ids?.length) payload.employee_ids = []
    updateMutation.mutate({ id: editingId, data: payload })
  }

  const handleCancelEdit = () => {
    setEditingId(null)
    setEditingForm(emptyForm)
    setError('')
  }

  const handleCreate = () => {
    if (!isValidForm(form)) {
      setError(t('admin.fillRequired'))
      return
    }
    const payload = { ...form }
    if (payload.manager_id === '' || payload.manager_id === undefined) delete payload.manager_id
    if (!payload.employee_ids?.length) delete payload.employee_ids
    createMutation.mutate(payload)
  }

  const managerOptions = [
    { value: '', label: t('admin.noManager') },
    ...employees.map((e) => ({ value: e._id, label: `${e.name} (${e.email})` })),
  ]

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.warehouses')}</h1>
        <button
          type="button"
          onClick={() => setShowForm(true)}
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 shrink-0 self-start"
        >
          {t('admin.addWarehouse')}
        </button>
      </div>
      <AdminListSearchBar
        value={searchInput}
        onChange={setSearchInput}
        placeholder={t('admin.searchWarehousesPlaceholder')}
        hint={
          isWarehouseManagerUser
            ? t('admin.searchWarehousesLocalHint')
            : t('admin.listAutoSearchHint')
        }
        isFetching={isFetching && !isWarehouseManagerUser}
        committedValue={committedSearch}
        onCommit={commitSearch}
        className="mb-6"
      />
      {editingId && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.editWarehouse')}</h2>
          <div className="grid gap-4 max-w-2xl">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.name')}</label>
                <input
                  type="text"
                  value={editingForm.name}
                  onChange={(e) => setEditingForm((p) => ({ ...p, name: e.target.value }))}
                  placeholder={t('admin.warehouseName')}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.email')}</label>
                <input
                  type="email"
                  value={editingForm.email}
                  onChange={(e) => setEditingForm((p) => ({ ...p, email: e.target.value }))}
                  placeholder="email@example.com"
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.address')}</label>
              <input
                type="text"
                value={editingForm.address}
                onChange={(e) => setEditingForm((p) => ({ ...p, address: e.target.value }))}
                placeholder={t('admin.address')}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.city')}</label>
                <input
                  type="text"
                  value={editingForm.city}
                  onChange={(e) => setEditingForm((p) => ({ ...p, city: e.target.value }))}
                  placeholder={t('admin.city')}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.country')}</label>
                <input
                  type="text"
                  value={editingForm.country}
                  onChange={(e) => setEditingForm((p) => ({ ...p, country: e.target.value }))}
                  placeholder={t('admin.country')}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.phone')} ({t('admin.optional')})</label>
              <input
                type="text"
                value={editingForm.phone ?? ''}
                onChange={(e) => setEditingForm((p) => ({ ...p, phone: e.target.value }))}
                placeholder={t('admin.phone')}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.manager')} ({t('admin.optional')})</label>
              <select
                value={editingForm.manager_id ?? ''}
                onChange={(e) => setEditingForm((p) => ({ ...p, manager_id: e.target.value || null }))}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              >
                {managerOptions.map((opt) => (
                  <option key={opt.value || 'none'} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.employees')} ({t('admin.optional')})</label>
              <p className="text-stone-500 text-sm mb-1">{t('admin.selectEmployeesForWarehouse')}</p>
              <select
                multiple
                value={editingForm.employee_ids ?? []}
                onChange={(e) => {
                  const selected = Array.from(e.target.selectedOptions, (o) => o.value)
                  setEditingForm((p) => ({ ...p, employee_ids: selected }))
                }}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg min-h-[120px]"
              >
                {employees.map((e) => (
                  <option key={e._id} value={e._id}>
                    {e.name} ({e.email})
                  </option>
                ))}
              </select>
            </div>
            {error && editingId && <p className="text-red-600 text-sm">{error}</p>}
            <div className="flex gap-2">
              <button
                type="button"
                onClick={handleSaveEdit}
                disabled={updateMutation.isPending || !isValidForm(editingForm)}
                className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
              >
                {updateMutation.isPending ? t('common.saving') : t('admin.update')}
              </button>
              <button type="button" onClick={handleCancelEdit} className="px-4 py-2 border border-stone-300 rounded-lg">
                {t('admin.cancel')}
              </button>
            </div>
          </div>
        </div>
      )}
      {showForm && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.newWarehouse')}</h2>
          <div className="grid gap-4 max-w-2xl">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.name')}</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                  placeholder={t('admin.warehouseName')}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.email')}</label>
                <input
                  type="email"
                  value={form.email}
                  onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))}
                  placeholder="email@example.com"
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.address')}</label>
              <input
                type="text"
                value={form.address}
                onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))}
                placeholder={t('admin.address')}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.city')}</label>
                <input
                  type="text"
                  value={form.city}
                  onChange={(e) => setForm((p) => ({ ...p, city: e.target.value }))}
                  placeholder={t('admin.city')}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.country')}</label>
                <input
                  type="text"
                  value={form.country}
                  onChange={(e) => setForm((p) => ({ ...p, country: e.target.value }))}
                  placeholder={t('admin.country')}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.phone')} ({t('admin.optional')})</label>
              <input
                type="text"
                value={form.phone ?? ''}
                onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))}
                placeholder={t('admin.phone')}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.manager')} ({t('admin.optional')})</label>
              <select
                value={form.manager_id ?? ''}
                onChange={(e) => setForm((p) => ({ ...p, manager_id: e.target.value || null }))}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              >
                {managerOptions.map((opt) => (
                  <option key={opt.value || 'none'} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.employees')} ({t('admin.optional')})</label>
              <p className="text-stone-500 text-sm mb-1">{t('admin.selectEmployeesForWarehouse')}</p>
              <select
                multiple
                value={form.employee_ids ?? []}
                onChange={(e) => {
                  const selected = Array.from(e.target.selectedOptions, (o) => o.value)
                  setForm((p) => ({ ...p, employee_ids: selected }))
                }}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg min-h-[120px]"
              >
                {employees.map((e) => (
                  <option key={e._id} value={e._id}>
                    {e.name} ({e.email})
                  </option>
                ))}
              </select>
            </div>
            {error && <p className="text-red-600 text-sm">{error}</p>}
            <div className="flex gap-2">
              <button
                type="button"
                onClick={handleCreate}
                disabled={createMutation.isPending || !isValidForm(form)}
                className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
              >
                {createMutation.isPending ? t('common.saving') : t('admin.create')}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowForm(false)
                  setForm(emptyForm)
                  setError('')
                }}
                className="px-4 py-2 border border-stone-300 rounded-lg"
              >
                {t('admin.cancel')}
              </button>
            </div>
          </div>
        </div>
      )}
      <div className="bg-white rounded-lg border border-stone-200 overflow-x-auto">
        <table className="w-full min-w-[600px]">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">{t('admin.name')}</th>
              <th className="px-4 py-2 text-left">{t('admin.address')}</th>
              <th className="px-4 py-2 text-left">{t('admin.city')}</th>
              <th className="px-4 py-2 text-left">{t('admin.country')}</th>
              <th className="px-4 py-2 text-left">{t('admin.email')}</th>
              <th className="px-4 py-2 text-left">{t('admin.manager')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((w) => (
              <tr key={w._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{w.name}</td>
                <td className="px-4 py-2">{w.address ?? '-'}</td>
                <td className="px-4 py-2">{w.city ?? '-'}</td>
                <td className="px-4 py-2">{w.country ?? '-'}</td>
                <td className="px-4 py-2">{w.email ?? '-'}</td>
                <td className="px-4 py-2">{w.manager ? `${w.manager.name} (${w.manager.email})` : '-'}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    onClick={() => handleStartEdit(w)}
                    className="text-amber-700 hover:underline text-sm mr-3"
                  >
                    {t('admin.edit')}
                  </button>
                  <button
                    type="button"
                    onClick={() =>
                      window.confirm(t('admin.deleteWarehouseConfirm', { name: w.name })) &&
                      deleteMutation.mutate(w._id)
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
        <p className="text-center text-stone-500 py-8">{t('admin.noWarehousesList')}</p>
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
