import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { AdminListSearchBar } from '../components/AdminListSearchBar'
import { useSearchCommit } from '../hooks/useSearchCommit'

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return Array.isArray(d) ? d : []
}

const EMPLOYEE_ROLES = [
  { value: 'manager', labelKey: 'admin.roleManager' },
  { value: 'shipping', labelKey: 'admin.roleShipping' },
  { value: 'review', labelKey: 'admin.roleReview' },
  { value: 'accounting', labelKey: 'admin.roleAccounting' },
  { value: 'warehouse_manager', labelKey: 'admin.roleWarehouseManager' },
] as const

type CustomerItem = {
  _id: string
  name: string
  email: string
  phone?: string
  address?: string
  city?: string
  country?: string
  postal_code?: string
}

export function AdminCustomers() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()

  useEffect(() => {
    setPage(1)
  }, [committedSearch])
  const [editingId, setEditingId] = useState<string | null>(null)
  const [convertingId, setConvertingId] = useState<string | null>(null)
  const [error, setError] = useState('')

  const [editForm, setEditForm] = useState({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    country: '',
    postal_code: '',
    password: '',
    password_confirmation: '',
  })

  const [convertForm, setConvertForm] = useState({
    role: 'shipping',
    warehouse_id: '',
  })

  const { data: customersData, isLoading, isFetching } = useQuery({
    queryKey: ['admin-customers', page, committedSearch],
    queryFn: async () => {
      const res = await admin.customers.list({
        page,
        per_page: 15,
        ...(committedSearch ? { search: committedSearch } : {}),
      })
      return res.data
    },
  })

  const { data: warehousesData } = useQuery({
    queryKey: ['admin-warehouses'],
    queryFn: async () => {
      const res = await admin.warehouses.list({ per_page: 100 })
      return res.data
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Parameters<typeof admin.customers.update>[1] }) => admin.customers.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-customers'] })
      setEditingId(null)
      setError('')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.customers.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-customers'] })
      setError('')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedDeleteCustomer'))
    },
  })

  const convertMutation = useMutation({
    mutationFn: ({
      id,
      data,
    }: {
      id: string
      data: { role: string; warehouse_id: string }
    }) => admin.customers.convertToEmployee(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-customers'] })
      queryClient.invalidateQueries({ queryKey: ['admin-employees'] })
      setConvertingId(null)
      setConvertForm({ role: 'shipping', warehouse_id: '' })
      setError('')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedConvertCustomer'))
    },
  })

  const items = extractList<CustomerItem>(customersData)
  const warehouses = extractList<{ _id: string; name: string }>(warehousesData)

  const startEdit = (c: CustomerItem) => {
    setEditingId(c._id)
    setConvertingId(null)
    setError('')
    setEditForm({
      name: c.name ?? '',
      email: c.email ?? '',
      phone: c.phone ?? '',
      address: c.address ?? '',
      city: c.city ?? '',
      country: c.country ?? '',
      postal_code: c.postal_code ?? '',
      password: '',
      password_confirmation: '',
    })
  }

  const startConvert = (id: string) => {
    setConvertingId(id)
    setEditingId(null)
    setError('')
    setConvertForm({ role: 'shipping', warehouse_id: '' })
  }

  const submitEdit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!editingId || !editForm.name.trim() || !editForm.email.trim()) {
      setError(t('admin.fillRequired'))
      return
    }
    if (editForm.password && editForm.password !== editForm.password_confirmation) {
      setError(t('auth.passwordsMismatch'))
      return
    }
    if (editForm.password && editForm.password.length < 8) {
      setError(t('admin.passwordMinLength'))
      return
    }
    const payload: Parameters<typeof admin.customers.update>[1] = {
      name: editForm.name,
      email: editForm.email,
      phone: editForm.phone || undefined,
      address: editForm.address || undefined,
      city: editForm.city || undefined,
      country: editForm.country || undefined,
      postal_code: editForm.postal_code || undefined,
    }
    if (editForm.password) {
      payload.password = editForm.password
      payload.password_confirmation = editForm.password_confirmation
    }
    updateMutation.mutate({ id: editingId, data: payload })
  }

  const submitConvert = (e: React.FormEvent) => {
    e.preventDefault()
    if (!convertingId || !convertForm.warehouse_id) {
      setError(t('admin.fillRequired'))
      return
    }
    convertMutation.mutate({ id: convertingId, data: convertForm })
  }

  if (isLoading && !customersData) return <div className="text-center py-12">{t('common.loading')}</div>

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.customers')}</h1>
      </div>
      <AdminListSearchBar
        value={searchInput}
        onChange={setSearchInput}
        placeholder={t('admin.searchCustomersPlaceholder')}
        hint={t('admin.searchCustomersHint')}
        ariaLabel={t('admin.searchCustomers')}
        isFetching={isFetching}
        committedValue={committedSearch}
        onCommit={commitSearch}
        className="mb-6"
      />

      {editingId && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.editCustomer')}</h2>
          <form onSubmit={submitEdit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input value={editForm.name} onChange={(e) => setEditForm((p) => ({ ...p, name: e.target.value }))} placeholder={t('admin.name')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input type="email" value={editForm.email} onChange={(e) => setEditForm((p) => ({ ...p, email: e.target.value }))} placeholder={t('admin.email')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input value={editForm.phone} onChange={(e) => setEditForm((p) => ({ ...p, phone: e.target.value }))} placeholder={t('admin.phone')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input value={editForm.address} onChange={(e) => setEditForm((p) => ({ ...p, address: e.target.value }))} placeholder={t('admin.address')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input value={editForm.city} onChange={(e) => setEditForm((p) => ({ ...p, city: e.target.value }))} placeholder={t('admin.city')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input value={editForm.country} onChange={(e) => setEditForm((p) => ({ ...p, country: e.target.value }))} placeholder={t('admin.country')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input value={editForm.postal_code} onChange={(e) => setEditForm((p) => ({ ...p, postal_code: e.target.value }))} placeholder={t('admin.postalCode')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input type="password" value={editForm.password} onChange={(e) => setEditForm((p) => ({ ...p, password: e.target.value }))} placeholder={t('admin.newPasswordOptional')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <input type="password" value={editForm.password_confirmation} onChange={(e) => setEditForm((p) => ({ ...p, password_confirmation: e.target.value }))} placeholder={t('admin.confirmNewPassword')} className="px-4 py-2 border border-stone-300 rounded-lg" />
            <div className="md:col-span-2 flex gap-2">
              <button type="submit" disabled={updateMutation.isPending} className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50">{t('admin.update')}</button>
              <button type="button" onClick={() => setEditingId(null)} className="px-4 py-2 border border-stone-300 rounded-lg">{t('admin.cancel')}</button>
            </div>
          </form>
        </div>
      )}

      {convertingId && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.convertCustomerToEmployee')}</h2>
          <p className="text-sm text-stone-600 mb-4 max-w-3xl">{t('admin.convertKeepsCustomerPassword')}</p>
          <form onSubmit={submitConvert} className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-3xl">
            <select
              value={convertForm.role}
              onChange={(e) => setConvertForm((p) => ({ ...p, role: e.target.value }))}
              className="px-4 py-2 border border-stone-300 rounded-lg"
            >
              {EMPLOYEE_ROLES.map((r) => (
                <option key={r.value} value={r.value}>{t(r.labelKey)}</option>
              ))}
            </select>
            <select
              value={convertForm.warehouse_id}
              onChange={(e) => setConvertForm((p) => ({ ...p, warehouse_id: e.target.value }))}
              className="px-4 py-2 border border-stone-300 rounded-lg"
            >
              <option value="">{t('admin.selectWarehouse')}</option>
              {warehouses.map((w) => (
                <option key={w._id} value={w._id}>{w.name}</option>
              ))}
            </select>
            <div className="md:col-span-2 flex gap-2">
              <button type="submit" disabled={convertMutation.isPending} className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50">{t('admin.convert')}</button>
              <button type="button" onClick={() => setConvertingId(null)} className="px-4 py-2 border border-stone-300 rounded-lg">{t('admin.cancel')}</button>
            </div>
          </form>
        </div>
      )}

      {error && <p className="mb-4 text-red-600 text-sm">{error}</p>}

      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">{t('admin.name')}</th>
              <th className="px-4 py-2 text-left">{t('admin.email')}</th>
              <th className="px-4 py-2 text-left">{t('admin.phone')}</th>
              <th className="px-4 py-2 text-left">{t('admin.location')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((c) => (
              <tr key={c._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{c.name}</td>
                <td className="px-4 py-2">{c.email}</td>
                <td className="px-4 py-2">{c.phone ?? '-'}</td>
                <td className="px-4 py-2">{[c.city, c.country].filter(Boolean).join(', ') || '-'}</td>
                <td className="px-4 py-2 text-right space-x-3">
                  <button type="button" onClick={() => startEdit(c)} className="text-amber-700 hover:underline text-sm">{t('admin.edit')}</button>
                  <button type="button" onClick={() => startConvert(c._id)} className="text-blue-700 hover:underline text-sm">{t('admin.convert')}</button>
                  <button
                    type="button"
                    onClick={() => {
                      if (window.confirm(t('admin.confirmDeleteCustomer'))) {
                        deleteMutation.mutate(c._id)
                      }
                    }}
                    className="text-red-700 hover:underline text-sm"
                  >
                    {t('admin.delete')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {items.length === 0 && <p className="text-center text-stone-500 py-8">{t('admin.noCustomers')}</p>}

      {(() => {
        const paginated = customersData?.data
        const meta = paginated && typeof paginated === 'object' && 'current_page' in paginated ? paginated : null
        return meta ? (
          <Pagination
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            perPage={meta.per_page}
            onPageChange={setPage}
          />
        ) : null
      })()}
    </div>
  )
}

