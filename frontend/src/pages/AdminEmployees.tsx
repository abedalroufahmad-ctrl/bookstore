import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { useAuth } from '../contexts/AuthContext'

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

export function AdminEmployees() {
  const { t } = useTranslation()
  const { user, userType } = useAuth()
  const isWarehouseManager = userType === 'employee' && (user as { role?: string } | null)?.role === 'warehouse_manager'
  const currentWarehouseId = (user as { warehouse_id?: string } | null)?.warehouse_id ?? ''
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'manager',
    warehouse_id: '',
    warehouse_ids: [] as string[],
  })
  const [error, setError] = useState('')

  const { data: employeesData, isLoading } = useQuery({
    queryKey: ['admin-employees', page],
    queryFn: async () => {
      const res = await admin.employees.list({ page, per_page: 15 })
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

  const createMutation = useMutation({
    mutationFn: (data: typeof form) => {
      const payload = { ...data }
      if (data.role === 'warehouse_manager' && data.warehouse_ids?.length) {
        delete (payload as Record<string, unknown>).warehouse_id
      } else {
        delete (payload as Record<string, unknown>).warehouse_ids
      }
      return admin.employees.create(payload as Parameters<typeof admin.employees.create>[0])
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-employees'] })
      setForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: isWarehouseManager ? 'shipping' : 'manager',
        warehouse_id: isWarehouseManager ? (warehouses[0]?._id ?? '') : '',
        warehouse_ids: [],
      })
      setShowForm(false)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  type EmployeeItem = { _id: string; name: string; email: string; role: string; warehouse_id?: string; warehouse_ids?: string[]; warehouse?: { _id: string; name: string } }
  const items = extractList<EmployeeItem>(employeesData)
  const warehouses = extractList<{ _id: string; name: string }>(warehousesData)
  const roleOptions = EMPLOYEE_ROLES
  const isWarehouseManagerRole = (r: string) => r === 'warehouse_manager'

  const [editingId, setEditingId] = useState<string | null>(null)
  const [editingForm, setEditingForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'manager',
    warehouse_id: '',
    warehouse_ids: [] as string[],
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data: d }: { id: string; data: Record<string, unknown> }) => {
      const payload = { ...d }
      if (d.role === 'warehouse_manager' && Array.isArray(d.warehouse_ids) && d.warehouse_ids.length) {
        delete payload.warehouse_id
      } else {
        delete payload.warehouse_ids
      }
      return admin.employees.update(id, payload as Parameters<typeof admin.employees.update>[1])
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-employees'] })
      setEditingId(null)
      setEditingForm({ name: '', email: '', password: '', password_confirmation: '', role: 'manager', warehouse_id: '', warehouse_ids: [] })
    },
    onError: (err: { response?: { data?: { message?: string; data?: { errors?: Record<string, string[]> } } } }) => {
      const d = err?.response?.data
      const msg = d?.message ?? t('admin.failedUpdate')
      const fieldErrors = d?.data?.errors
      const detail = fieldErrors && typeof fieldErrors === 'object'
        ? Object.values(fieldErrors).flat().join(' ')
        : ''
      setError(detail ? `${msg}: ${detail}` : msg)
    },
  })

  const handleStartEdit = (emp: EmployeeItem) => {
    setEditingId(emp._id)
    const validRole = EMPLOYEE_ROLES.some((r) => r.value === emp.role) ? emp.role : 'manager'
    setEditingForm({
      name: emp.name,
      email: emp.email,
      password: '',
      password_confirmation: '',
      role: validRole,
      warehouse_id: emp.warehouse_id ?? emp.warehouse?._id ?? '',
      warehouse_ids: Array.isArray(emp.warehouse_ids) ? emp.warehouse_ids : [],
    })
    setError('')
  }

  const handleSaveEdit = (e: React.FormEvent) => {
    e.preventDefault()
    const isWMRole = editingForm.role === 'warehouse_manager'
    const hasWarehouse = isWMRole
      ? (Array.isArray(editingForm.warehouse_ids) && editingForm.warehouse_ids.length > 0)
      : !!editingForm.warehouse_id
    if (!editingId || !editingForm.name.trim() || !editingForm.email.trim() || !hasWarehouse) {
      setError(t('admin.fillRequired'))
      return
    }
    const payload: Record<string, unknown> = {
      name: editingForm.name,
      email: editingForm.email,
      role: editingForm.role,
      ...(isWMRole ? { warehouse_ids: editingForm.warehouse_ids } : { warehouse_id: editingForm.warehouse_id }),
    }
    if (editingForm.password && editingForm.password.length >= 8) {
      if (editingForm.password !== editingForm.password_confirmation) {
        setError(t('auth.passwordsMismatch'))
        return
      }
      payload.password = editingForm.password
      payload.password_confirmation = editingForm.password_confirmation
    }
    setError('')
    updateMutation.mutate({ id: editingId, data: payload })
  }

  const handleCancelEdit = () => {
    setEditingId(null)
    setEditingForm({ name: '', email: '', password: '', password_confirmation: '', role: 'manager', warehouse_id: '', warehouse_ids: [] })
    setError('')
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    const isWMRole = form.role === 'warehouse_manager'
    const hasWarehouse = isWarehouseManager
      ? !!form.warehouse_id
      : isWMRole
        ? (Array.isArray(form.warehouse_ids) && form.warehouse_ids.length > 0)
        : !!form.warehouse_id
    if (!form.name.trim() || !form.email.trim() || !form.password || !hasWarehouse) {
      setError(t('admin.fillRequired'))
      return
    }
    if (form.password !== form.password_confirmation) {
      setError(t('auth.passwordsMismatch'))
      return
    }
    if (form.password.length < 8) {
      setError(t('admin.passwordMinLength'))
      return
    }
    createMutation.mutate({ ...form })
  }

  if (isLoading) return <div className="text-center py-12">{t('common.loading')}</div>

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.employees')}</h1>
        <button
          type="button"
          onClick={() => {
            setForm((prev) => ({
              ...prev,
              role: isWarehouseManager ? 'shipping' : prev.role,
              warehouse_id: isWarehouseManager ? (warehouses[0]?._id ?? '') : prev.warehouse_id,
              warehouse_ids: prev.warehouse_ids ?? [],
            }))
            setShowForm(true)
          }}
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          {t('admin.addEmployee')}
        </button>
      </div>
      {editingId && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.editEmployee')}</h2>
          <form onSubmit={handleSaveEdit} className="space-y-4 max-w-md">
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.name')}</label>
              <input
                type="text"
                value={editingForm.name}
                onChange={(e) => setEditingForm((p) => ({ ...p, name: e.target.value }))}
                required
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.email')}</label>
              <input
                type="email"
                value={editingForm.email}
                onChange={(e) => setEditingForm((p) => ({ ...p, email: e.target.value }))}
                required
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('auth.password')} ({t('admin.optional')})</label>
              <input
                type="password"
                value={editingForm.password}
                onChange={(e) => setEditingForm((p) => ({ ...p, password: e.target.value }))}
                minLength={8}
                placeholder={t('admin.leaveBlankToKeep')}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('auth.confirmPassword')} ({t('admin.optional')})</label>
              <input
                type="password"
                value={editingForm.password_confirmation}
                onChange={(e) => setEditingForm((p) => ({ ...p, password_confirmation: e.target.value }))}
                minLength={8}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.role')}</label>
              <select
                value={editingForm.role}
                onChange={(e) => setEditingForm((p) => ({ ...p, role: e.target.value }))}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              >
                {roleOptions.map((r) => (
                  <option key={r.value} value={r.value}>
                    {t(r.labelKey)}
                  </option>
                ))}
              </select>
              {isWarehouseManager && (
                <p className="mt-1 text-stone-500 text-sm">{t('admin.roleShipping')} only</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.warehouse')}</label>
              {isWarehouseManager ? (
                <select
                  value={editingForm.warehouse_id}
                  onChange={(e) => setEditingForm((p) => ({ ...p, warehouse_id: e.target.value }))}
                  required
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                >
                  <option value="">{t('admin.selectWarehouse')}</option>
                  {warehouses.map((w) => (
                    <option key={w._id} value={w._id}>
                      {w.name}
                    </option>
                  ))}
                </select>
              ) : isWarehouseManagerRole(editingForm.role) ? (
                <div className="space-y-2">
                  <select
                    multiple
                    value={editingForm.warehouse_ids}
                    onChange={(e) => {
                      const selected = Array.from(e.target.selectedOptions, (o) => o.value)
                      setEditingForm((p) => ({ ...p, warehouse_ids: selected }))
                    }}
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg min-h-[100px]"
                  >
                    {warehouses.map((w) => (
                      <option key={w._id} value={w._id}>
                        {w.name}
                      </option>
                    ))}
                  </select>
                  <p className="text-stone-500 text-sm">{t('admin.holdCtrlToSelectMultiple')}</p>
                </div>
              ) : (
                <select
                  value={editingForm.warehouse_id}
                  onChange={(e) => setEditingForm((p) => ({ ...p, warehouse_id: e.target.value }))}
                  required
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                >
                  <option value="">{t('admin.selectWarehouse')}</option>
                  {warehouses.map((w) => (
                    <option key={w._id} value={w._id}>
                      {w.name}
                    </option>
                  ))}
                </select>
              )}
            </div>
            {error && editingId && <p className="text-red-600 text-sm">{error}</p>}
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={
                  updateMutation.isPending ||
                  !editingForm.name.trim() ||
                  !editingForm.email.trim() ||
                  (isWarehouseManagerRole(editingForm.role)
                    ? !(editingForm.warehouse_ids?.length)
                    : !(isWarehouseManager ? editingForm.warehouse_id : editingForm.warehouse_id))
                }
                className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
              >
                {updateMutation.isPending ? t('common.saving') : t('admin.update')}
              </button>
              <button type="button" onClick={handleCancelEdit} className="px-4 py-2 border border-stone-300 rounded-lg">
                {t('admin.cancel')}
              </button>
            </div>
          </form>
        </div>
      )}
      {showForm && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.newEmployee')}</h2>
          <form onSubmit={handleSubmit} className="space-y-4 max-w-md">
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.name')}</label>
              <input
                type="text"
                value={form.name}
                onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                required
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.email')}</label>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))}
                required
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('auth.password')}</label>
              <input
                type="password"
                value={form.password}
                onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))}
                required
                minLength={8}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('auth.confirmPassword')}</label>
              <input
                type="password"
                value={form.password_confirmation}
                onChange={(e) => setForm((p) => ({ ...p, password_confirmation: e.target.value }))}
                required
                minLength={8}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.role')}</label>
              <select
                value={form.role}
                onChange={(e) => setForm((p) => ({ ...p, role: e.target.value }))}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg"
              >
                {roleOptions.map((r) => (
                  <option key={r.value} value={r.value}>
                    {t(r.labelKey)}
                  </option>
                ))}
              </select>
              {isWarehouseManager && (
                <p className="mt-1 text-stone-500 text-sm">{t('admin.roleShipping')} only</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.warehouse')}</label>
              {isWarehouseManager ? (
                <select
                  value={form.warehouse_id}
                  onChange={(e) => setForm((p) => ({ ...p, warehouse_id: e.target.value }))}
                  required
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                >
                  <option value="">{t('admin.selectWarehouse')}</option>
                  {warehouses.map((w) => (
                    <option key={w._id} value={w._id}>
                      {w.name}
                    </option>
                  ))}
                </select>
              ) : isWarehouseManagerRole(form.role) ? (
                <div className="space-y-2">
                  <select
                    multiple
                    value={form.warehouse_ids}
                    onChange={(e) => {
                      const selected = Array.from(e.target.selectedOptions, (o) => o.value)
                      setForm((p) => ({ ...p, warehouse_ids: selected }))
                    }}
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg min-h-[100px]"
                  >
                    {warehouses.map((w) => (
                      <option key={w._id} value={w._id}>
                        {w.name}
                      </option>
                    ))}
                  </select>
                  <p className="text-stone-500 text-sm">{t('admin.holdCtrlToSelectMultiple')}</p>
                </div>
              ) : (
                <>
                  <select
                    value={form.warehouse_id}
                    onChange={(e) => setForm((p) => ({ ...p, warehouse_id: e.target.value }))}
                    required
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                  >
                    <option value="">{t('admin.selectWarehouse')}</option>
                    {warehouses.map((w) => (
                      <option key={w._id} value={w._id}>
                        {w.name}
                      </option>
                    ))}
                  </select>
                  {warehouses.length === 0 && (
                    <p className="mt-1 text-amber-700 text-sm">{t('admin.noWarehouses')}</p>
                  )}
                </>
              )}
            </div>
            {error && <p className="text-red-600 text-sm">{error}</p>}
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={createMutation.isPending}
                className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
              >
                {createMutation.isPending ? t('common.saving') : t('admin.create')}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowForm(false)
                  setForm({
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                    role: 'manager',
                    warehouse_id: '',
                    warehouse_ids: [],
                  })
                  setError('')
                }}
                className="px-4 py-2 border border-stone-300 rounded-lg"
              >
                {t('admin.cancel')}
              </button>
            </div>
          </form>
        </div>
      )}
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">{t('admin.name')}</th>
              <th className="px-4 py-2 text-left">{t('admin.email')}</th>
              <th className="px-4 py-2 text-left">{t('admin.role')}</th>
              <th className="px-4 py-2 text-left">{t('admin.warehouse')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((emp) => (
              <tr key={emp._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{emp.name}</td>
                <td className="px-4 py-2">{emp.email}</td>
                <td className="px-4 py-2">{t(EMPLOYEE_ROLES.find((r) => r.value === emp.role)?.labelKey ?? emp.role)}</td>
                <td className="px-4 py-2">{emp.warehouse?.name ?? '-'}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    onClick={() => handleStartEdit(emp)}
                    className="text-amber-700 hover:underline text-sm"
                  >
                    {t('admin.edit')}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 && !showForm && (
        <p className="text-center text-stone-500 py-8">{t('admin.noEmployees')}</p>
      )}
      {(() => {
        const paginated = employeesData?.data
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
