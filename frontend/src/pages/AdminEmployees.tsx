import { useEffect, useMemo, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin } from '../lib/api'
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
  return Array.isArray(d) ? d : []
}

const EMPLOYEE_ROLES = [
  { value: 'manager', labelKey: 'admin.roleManager' },
  { value: 'shipping', labelKey: 'admin.roleShipping' },
  { value: 'review', labelKey: 'admin.roleReview' },
  { value: 'accounting', labelKey: 'admin.roleAccounting' },
  { value: 'warehouse_manager', labelKey: 'admin.roleWarehouseManager' },
] as const

/** Roles a warehouse_manager may assign to new staff in their warehouse(s) */
const WAREHOUSE_MANAGER_STAFF_ROLE_VALUES = ['shipping', 'accounting'] as const

export function AdminEmployees() {
  const { t } = useTranslation()
  const { user, userType } = useAuth()
  const isWarehouseManager = userType === 'employee' && (user as { role?: string } | null)?.role === 'warehouse_manager'
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const { searchInput, setSearchInput, committedSearch, commitSearch } = useSearchCommit()
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

  useEffect(() => {
    setPage(1)
  }, [committedSearch])

  const { data: employeesData, isLoading, isFetching } = useQuery({
    queryKey: ['admin-employees', page, committedSearch],
    queryFn: async () => {
      const res = await admin.employees.list({
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
  const roleOptions = isWarehouseManager
    ? EMPLOYEE_ROLES.filter((r) =>
        (WAREHOUSE_MANAGER_STAFF_ROLE_VALUES as readonly string[]).includes(r.value)
      )
    : EMPLOYEE_ROLES
  const isWarehouseManagerRole = (r: string) => r === 'warehouse_manager'

  const managedWarehouseIds = useMemo(() => {
    if (!isWarehouseManager || !user) return [] as string[]
    const u = user as { warehouse_id?: string; warehouse_ids?: string[]; role?: string }
    if (u.role === 'warehouse_manager' && Array.isArray(u.warehouse_ids) && u.warehouse_ids.length > 0) {
      return u.warehouse_ids.map(String)
    }
    if (u.warehouse_id) return [String(u.warehouse_id)]
    return []
  }, [isWarehouseManager, user])

  const isEmployeeInManagedWarehouse = (emp: EmployeeItem) => {
    if (!emp.warehouse_id) return false
    return managedWarehouseIds.includes(String(emp.warehouse_id))
  }

  const isWarehouseManagerEditBlocked = (emp: EmployeeItem) =>
    isWarehouseManager && emp.role === 'warehouse_manager' && !isEmployeeInManagedWarehouse(emp)

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
    const validRole = isWarehouseManager
      ? emp.role === 'shipping' || emp.role === 'accounting'
        ? emp.role
        : 'shipping'
      : EMPLOYEE_ROLES.some((r) => r.value === emp.role)
        ? emp.role
        : 'manager'
    // Always prefer the employee's actual warehouse for the dropdown value (string for <select> matching).
    const employeeWarehouseId = String(emp.warehouse_id ?? emp.warehouse?._id ?? '').trim()
    const defaultWarehouseForAssign =
      warehouses.find((w) => managedWarehouseIds.includes(String(w._id)))?._id ?? warehouses[0]?._id ?? ''
    // Only warehouse managers use "assign to my warehouse" default when the employee is outside their warehouses.
    // For other roles, managedWarehouseIds is empty so inManaged would always be false — that wrongly picked warehouses[0].
    const inManaged = isWarehouseManager && isEmployeeInManagedWarehouse(emp)
    const warehouseIdForEdit =
      isWarehouseManager && ! inManaged
        ? String(defaultWarehouseForAssign)
        : employeeWarehouseId
    setEditingForm({
      name: emp.name,
      email: emp.email,
      password: '',
      password_confirmation: '',
      role: validRole,
      warehouse_id: warehouseIdForEdit,
      warehouse_ids: Array.isArray(emp.warehouse_ids) ? emp.warehouse_ids.map(String) : [],
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

  if (isLoading && !employeesData) return <div className="text-center py-12">{t('common.loading')}</div>

  const editingEmp = editingId ? items.find((e) => e._id === editingId) : undefined
  const showAssignFromDirectoryHint =
    Boolean(isWarehouseManager && editingId && editingEmp && !isEmployeeInManagedWarehouse(editingEmp))

  return (
    <div>
      <div className="flex flex-col gap-4 mb-6">
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <h1 className="text-2xl font-bold text-amber-900">{t('admin.employees')}</h1>
          <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto">
            <AdminListSearchBar
              value={searchInput}
              onChange={setSearchInput}
              placeholder={t('admin.searchEmployeesPlaceholder')}
              hint={t('admin.listAutoSearchHint')}
              isFetching={isFetching}
              committedValue={committedSearch}
              onCommit={commitSearch}
              className="w-full sm:min-w-[280px]"
            />
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
              className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 whitespace-nowrap"
            >
              {t('admin.addEmployee')}
            </button>
          </div>
        </div>
        {isWarehouseManager && (
          <p className="text-sm text-stone-600">{t('admin.employeesAllUsersHint')}</p>
        )}
      </div>
      {editingId && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-4">{t('admin.editEmployee')}</h2>
          {showAssignFromDirectoryHint && (
            <p className="mb-4 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
              {t('admin.assignEmployeeToWarehouseHint')}
            </p>
          )}
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
                <p className="mt-1 text-stone-500 text-sm">{t('admin.warehouseManagerStaffRolesHint')}</p>
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
                <p className="mt-1 text-stone-500 text-sm">{t('admin.warehouseManagerStaffRolesHint')}</p>
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
                    disabled={isWarehouseManagerEditBlocked(emp)}
                    title={isWarehouseManagerEditBlocked(emp) ? t('admin.cannotEditOtherWarehouseManager') : undefined}
                    className="text-amber-700 hover:underline text-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:no-underline"
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
