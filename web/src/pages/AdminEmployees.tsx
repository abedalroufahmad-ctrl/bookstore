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
  if (Array.isArray(data)) return data as T[]
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object') {
    const inner = (d.data as { data?: unknown }).data
    if (Array.isArray(inner)) return inner as T[]
  }
  return []
}

const EMPLOYEE_ROLES = [
  { value: 'manager', labelKey: 'admin.roleManager' },
  { value: 'shipping', labelKey: 'admin.roleShipping' },
  { value: 'review', labelKey: 'admin.roleReview' },
  { value: 'accounting', labelKey: 'admin.roleAccounting' },
  { value: 'warehouse_manager', labelKey: 'admin.roleWarehouseManager' },
  { value: 'publisher_manager', labelKey: 'admin.rolePublisherManager' },
  { value: 'direct_sales', labelKey: 'admin.roleDirectSales' },
] as const

/** Roles a warehouse_manager may assign to new staff in their warehouse(s) */
const WAREHOUSE_MANAGER_STAFF_ROLE_VALUES = ['shipping', 'accounting', 'direct_sales'] as const

/** Roles a publisher_manager may assign to staff linked to their publisher */
const PUBLISHER_MANAGER_STAFF_ROLE_VALUES = [
  'shipping',
  'review',
  'accounting',
  'warehouse_manager',
  'publisher_manager',
  'direct_sales',
] as const

export function AdminEmployees() {
  const { t } = useTranslation()
  const { user, userType } = useAuth()
  const employeeRole = userType === 'employee' ? (user as { role?: string } | null)?.role : undefined
  const isWarehouseManager = employeeRole === 'warehouse_manager'
  const isPublisherManager = employeeRole === 'publisher_manager'
  const managedPublisherId = isPublisherManager
    ? String((user as { publisher_id?: string } | null)?.publisher_id ?? '')
    : ''
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
    publisher_id: '',
  })
  const [error, setError] = useState('')

  useEffect(() => {
    setPage(1)
  }, [committedSearch])

  const defaultRoleForActor = () => {
    if (isWarehouseManager) return 'shipping'
    if (isPublisherManager) return 'shipping'
    return 'manager'
  }

  const { data: employeesData, isLoading, isFetching, error: employeesError } = useQuery({
    queryKey: ['admin-employees', page, committedSearch],
    queryFn: async () => {
      const res = await admin.employees.list({
        page,
        per_page: 25,
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

  const { data: publishersData } = useQuery({
    queryKey: ['admin-publishers-all'],
    queryFn: async () => {
      const res = await admin.publishers.list({ per_page: 200 })
      return res.data
    },
  })

  type EmployeeItem = {
    _id: string
    name: string
    email: string
    role: string
    warehouse_id?: string
    warehouse_ids?: string[]
    publisher_id?: string
    warehouse?: { _id: string; name: string; publisher_id?: string; publisher?: { _id: string; name: string } }
    publisher?: { _id: string; name: string }
  }
  const items = extractList<EmployeeItem>(employeesData)
  const warehouses = extractList<{ _id: string; name: string; publisher_id?: string }>(warehousesData)
  const publishers = extractList<{ _id: string; name: string }>(publishersData)

  const publisherNameForEmployee = (emp: EmployeeItem) => {
    if (emp.publisher?.name) return emp.publisher.name
    if (emp.publisher_id) {
      const fromList = publishers.find((p) => p._id === emp.publisher_id)?.name
      if (fromList) return fromList
    }
    if (emp.warehouse?.publisher?.name) return emp.warehouse.publisher.name
    const warehousePublisherId =
      emp.warehouse?.publisher_id ??
      warehouses.find((w) => w._id === emp.warehouse_id)?.publisher_id
    if (warehousePublisherId) {
      return publishers.find((p) => p._id === warehousePublisherId)?.name ?? '-'
    }
    return '-'
  }

  const warehouseNameForEmployee = (emp: EmployeeItem) => {
    if (Array.isArray(emp.warehouse_ids) && emp.warehouse_ids.length > 0) {
      const names = emp.warehouse_ids
        .map((id) => warehouses.find((w) => w._id === id)?.name)
        .filter(Boolean)
      return names.length ? names.join(', ') : '-'
    }
    if (emp.warehouse?.name) return emp.warehouse.name
    if (emp.warehouse_id) {
      return warehouses.find((w) => w._id === emp.warehouse_id)?.name ?? '-'
    }
    return '-'
  }

  const isManager = employeeRole === 'manager'
  const canEditPublisher = isManager || isPublisherManager
  const publisherOptions = publishers

  const warehousesForPublisher = (publisherId: string) => {
    if (!publisherId) return warehouses
    return warehouses.filter((w) => String(w.publisher_id ?? '') === String(publisherId))
  }

  const resolveEmployeePublisherId = (emp: EmployeeItem) => {
    if (emp.publisher_id) return String(emp.publisher_id)
    if (emp.publisher?._id) return String(emp.publisher._id)
    if (emp.warehouse?.publisher_id) return String(emp.warehouse.publisher_id)
    if (emp.warehouse?.publisher?._id) return String(emp.warehouse.publisher._id)
    const fromWarehouse = warehouses.find((w) => w._id === emp.warehouse_id)?.publisher_id
    return fromWarehouse ? String(fromWarehouse) : (isPublisherManager ? managedPublisherId : '')
  }

  const buildEmployeePayload = (
    data: {
      name: string
      email: string
      role: string
      warehouse_id: string
      warehouse_ids: string[]
      publisher_id: string
      password?: string
      password_confirmation?: string
    },
  ) => {
    const payload: Record<string, unknown> = {
      name: data.name,
      email: data.email,
      role: data.role,
    }
    const publisherId = data.publisher_id
    if (data.role === 'publisher_manager') {
      payload.publisher_id = publisherId
    } else if (data.role === 'warehouse_manager' || data.role === 'shipping' || data.role === 'direct_sales') {
      payload.warehouse_ids = data.warehouse_ids
      if (canEditPublisher && publisherId) payload.publisher_id = publisherId
    } else {
      payload.warehouse_id = data.warehouse_id
      if (canEditPublisher && publisherId) payload.publisher_id = publisherId
    }
    if (data.password && data.password.length >= 8) {
      payload.password = data.password
      payload.password_confirmation = data.password_confirmation
    }
    return payload
  }

  const createMutation = useMutation({
    mutationFn: (data: typeof form) =>
      admin.employees.create(buildEmployeePayload(data) as Parameters<typeof admin.employees.create>[0]),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-employees'] })
      setForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: defaultRoleForActor(),
        warehouse_id: isWarehouseManager || isPublisherManager ? (warehouses[0]?._id ?? '') : '',
        warehouse_ids: [],
        publisher_id: isPublisherManager ? managedPublisherId : '',
      })
      setShowForm(false)
    },
    onError: (err: any) => {
      const d = err?.response?.data
      const msg = d?.message ?? t('admin.failedCreate')
      const fieldErrors = d?.data?.errors
      const detail = fieldErrors && typeof fieldErrors === 'object'
        ? Object.values(fieldErrors).flat().join(' ')
        : ''
      setError(detail ? `${msg}: ${detail}` : msg)
    },
  })

  const roleOptions = isWarehouseManager
    ? EMPLOYEE_ROLES.filter((r) =>
        (WAREHOUSE_MANAGER_STAFF_ROLE_VALUES as readonly string[]).includes(r.value)
      )
    : isPublisherManager
      ? EMPLOYEE_ROLES.filter((r) =>
          (PUBLISHER_MANAGER_STAFF_ROLE_VALUES as readonly string[]).includes(r.value)
        )
      : EMPLOYEE_ROLES
  const isWarehouseManagerRole = (r: string) => r === 'warehouse_manager'
  const isPublisherManagerRole = (r: string) => r === 'publisher_manager'

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

  const isPublisherManagerEditBlocked = (emp: EmployeeItem) =>
    isPublisherManager && emp.role === 'manager'

  const isCurrentUser = (emp: EmployeeItem) =>
    !!user && String((user as { id?: string }).id) === String(emp._id)

  const canDeleteEmployee = (emp: EmployeeItem) => {
    if (isCurrentUser(emp)) return false
    if (isWarehouseManager) {
      return (
        isEmployeeInManagedWarehouse(emp) &&
        (WAREHOUSE_MANAGER_STAFF_ROLE_VALUES as readonly string[]).includes(emp.role)
      )
    }
    if (isPublisherManager) {
      return (PUBLISHER_MANAGER_STAFF_ROLE_VALUES as readonly string[]).includes(emp.role)
    }
    return true
  }

  const [editingId, setEditingId] = useState<string | null>(null)
  const [editingForm, setEditingForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'manager',
    warehouse_id: '',
    warehouse_ids: [] as string[],
    publisher_id: '',
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data: d }: { id: string; data: Parameters<typeof buildEmployeePayload>[0] }) =>
      admin.employees.update(id, buildEmployeePayload(d) as Parameters<typeof admin.employees.update>[1]),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-employees'] })
      setEditingId(null)
      setEditingForm({ name: '', email: '', password: '', password_confirmation: '', role: 'manager', warehouse_id: '', warehouse_ids: [], publisher_id: '' })
    },
    onError: (err: any) => {
      const d = err?.response?.data
      const msg = d?.message ?? t('admin.failedUpdate')
      const fieldErrors = d?.data?.errors
      const detail = fieldErrors && typeof fieldErrors === 'object'
        ? Object.values(fieldErrors).flat().join(' ')
        : ''
      setError(detail ? `${msg}: ${detail}` : msg)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.employees.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-employees'] })
      setError('')
    },
    onError: (err: any) => {
      const d = err?.response?.data
      const msg = d?.message ?? t('admin.failedDeleteEmployee')
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
      : isPublisherManager
        ? (PUBLISHER_MANAGER_STAFF_ROLE_VALUES as readonly string[]).includes(emp.role)
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
      publisher_id: resolveEmployeePublisherId(emp),
    })
    setError('')
  }

  const handleSaveEdit = (e: React.FormEvent) => {
    e.preventDefault()
    const isWMRole = editingForm.role === 'warehouse_manager' || editingForm.role === 'shipping' || editingForm.role === 'direct_sales'
    const isPMRole = editingForm.role === 'publisher_manager'
    const hasScope = isWMRole
      ? (Array.isArray(editingForm.warehouse_ids) && editingForm.warehouse_ids.length > 0)
      : isPMRole
        ? !!editingForm.publisher_id
        : !!editingForm.warehouse_id
    if (canEditPublisher && !editingForm.publisher_id) {
      setError(t('admin.fillRequired'))
      return
    }
    if (!editingId || !editingForm.name.trim() || !editingForm.email.trim() || !hasScope) {
      setError(t('admin.fillRequired'))
      return
    }
    if (editingForm.password && editingForm.password.length >= 8) {
      if (editingForm.password !== editingForm.password_confirmation) {
        setError(t('auth.passwordsMismatch'))
        return
      }
    }
    setError('')
    updateMutation.mutate({ id: editingId, data: editingForm })
  }

  const handleCancelEdit = () => {
    setEditingId(null)
    setEditingForm({ name: '', email: '', password: '', password_confirmation: '', role: 'manager', warehouse_id: '', warehouse_ids: [], publisher_id: '' })
    setError('')
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    const isWMRole = form.role === 'warehouse_manager' || form.role === 'shipping' || form.role === 'direct_sales'
    const isPMRole = form.role === 'publisher_manager'
    const hasWarehouse = isWarehouseManager
      ? !!form.warehouse_id
      : isWMRole
        ? (Array.isArray(form.warehouse_ids) && form.warehouse_ids.length > 0)
        : isPMRole
          ? !!form.publisher_id
          : !!form.warehouse_id
    if (canEditPublisher && !form.publisher_id) {
      setError(t('admin.fillRequired'))
      return
    }
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
  const employeesErrorMessage =
    (employeesError as { response?: { data?: { message?: string } } } | null)?.response?.data?.message

  return (
    <div>
      {employeesErrorMessage && (
        <p className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
          {employeesErrorMessage}
        </p>
      )}
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
                  role: defaultRoleForActor(),
                  warehouse_id:
                    isWarehouseManager || isPublisherManager
                      ? (warehouses[0]?._id ?? '')
                      : prev.warehouse_id,
                  warehouse_ids: prev.warehouse_ids ?? [],
                  publisher_id: isPublisherManager ? managedPublisherId : prev.publisher_id,
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
        {isPublisherManager && (
          <p className="text-sm text-stone-600">{t('admin.employeesPublisherScopeHint')}</p>
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
              {isPublisherManager && (
                <p className="mt-1 text-stone-500 text-sm">{t('admin.publisherManagerStaffRolesHint')}</p>
              )}
            </div>
            <div className="space-y-4">
              {canEditPublisher && (
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.publisher')}</label>
                  <select
                    value={editingForm.publisher_id}
                    onChange={(e) => {
                      const nextPublisherId = e.target.value
                      const allowed = warehousesForPublisher(nextPublisherId)
                      setEditingForm((p) => ({
                        ...p,
                        publisher_id: nextPublisherId,
                        warehouse_id: allowed.some((w) => w._id === p.warehouse_id) ? p.warehouse_id : '',
                        warehouse_ids: (p.warehouse_ids ?? []).filter((id) =>
                          allowed.some((w) => w._id === id),
                        ),
                      }))
                    }}
                    required
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                  >
                    <option value="">{t('admin.selectPublisher')}</option>
                    {publisherOptions.map((p) => (
                      <option key={p._id} value={p._id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                  {isPublisherManagerRole(editingForm.role) && (
                    <p className="mt-1 text-stone-500 text-sm">{t('admin.publisherManagerScopeHint')}</p>
                  )}
                </div>
              )}
              {!isPublisherManagerRole(editingForm.role) && (
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
                  ) : (isWarehouseManagerRole(editingForm.role) || editingForm.role === 'shipping' || editingForm.role === 'direct_sales') ? (
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
                        {warehousesForPublisher(editingForm.publisher_id).map((w) => (
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
                      {warehousesForPublisher(editingForm.publisher_id).map((w) => (
                        <option key={w._id} value={w._id}>
                          {w.name}
                        </option>
                      ))}
                    </select>
                  )}
                </div>
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
                  (canEditPublisher && !editingForm.publisher_id) ||
                  ((isWarehouseManagerRole(editingForm.role) || editingForm.role === 'shipping' || editingForm.role === 'direct_sales')
                    ? !(editingForm.warehouse_ids?.length)
                    : isPublisherManagerRole(editingForm.role)
                      ? !editingForm.publisher_id
                      : !editingForm.warehouse_id)
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
              {isPublisherManager && (
                <p className="mt-1 text-stone-500 text-sm">{t('admin.publisherManagerStaffRolesHint')}</p>
              )}
            </div>
            <div className="space-y-4">
              {canEditPublisher && (
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.publisher')}</label>
                  <select
                    value={form.publisher_id}
                    onChange={(e) => {
                      const nextPublisherId = e.target.value
                      const allowed = warehousesForPublisher(nextPublisherId)
                      setForm((p) => ({
                        ...p,
                        publisher_id: nextPublisherId,
                        warehouse_id: allowed.some((w) => w._id === p.warehouse_id) ? p.warehouse_id : '',
                        warehouse_ids: (p.warehouse_ids ?? []).filter((id) =>
                          allowed.some((w) => w._id === id),
                        ),
                      }))
                    }}
                    required
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                  >
                    <option value="">{t('admin.selectPublisher')}</option>
                    {publisherOptions.map((p) => (
                      <option key={p._id} value={p._id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                  {isPublisherManagerRole(form.role) && (
                    <p className="mt-1 text-stone-500 text-sm">{t('admin.publisherManagerScopeHint')}</p>
                  )}
                </div>
              )}
              {!isPublisherManagerRole(form.role) && (
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
                  ) : (isWarehouseManagerRole(form.role) || form.role === 'shipping' || form.role === 'direct_sales') ? (
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
                        {warehousesForPublisher(form.publisher_id).map((w) => (
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
                        {warehousesForPublisher(form.publisher_id).map((w) => (
                          <option key={w._id} value={w._id}>
                            {w.name}
                          </option>
                        ))}
                      </select>
                      {warehousesForPublisher(form.publisher_id).length === 0 && (
                        <p className="mt-1 text-amber-700 text-sm">{t('admin.noWarehouses')}</p>
                      )}
                    </>
                  )}
                </div>
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
                    role: defaultRoleForActor(),
                    warehouse_id: '',
                    warehouse_ids: [],
                    publisher_id: isPublisherManager ? managedPublisherId : '',
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
              <th className="px-4 py-2 text-left">{t('admin.publisher')}</th>
              <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((emp) => (
              <tr key={emp._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{emp.name}</td>
                <td className="px-4 py-2">{emp.email}</td>
                <td className="px-4 py-2">{t(EMPLOYEE_ROLES.find((r) => r.value === emp.role)?.labelKey ?? emp.role)}</td>
                <td className="px-4 py-2">{warehouseNameForEmployee(emp)}</td>
                <td className="px-4 py-2">{publisherNameForEmployee(emp)}</td>
                <td className="px-4 py-2 text-right space-x-3 rtl:space-x-reverse">
                  <button
                    type="button"
                    onClick={() => handleStartEdit(emp)}
                    disabled={isWarehouseManagerEditBlocked(emp) || isPublisherManagerEditBlocked(emp)}
                    title={
                      isWarehouseManagerEditBlocked(emp)
                        ? t('admin.cannotEditOtherWarehouseManager')
                        : isPublisherManagerEditBlocked(emp)
                          ? t('admin.cannotEditGlobalManager')
                          : undefined
                    }
                    className="text-amber-700 hover:underline text-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:no-underline"
                  >
                    {t('admin.edit')}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      if (!window.confirm(t('admin.confirmDeleteEmployee'))) return
                      deleteMutation.mutate(emp._id)
                    }}
                    disabled={!canDeleteEmployee(emp) || deleteMutation.isPending}
                    title={
                      isCurrentUser(emp)
                        ? t('admin.cannotDeleteSelf')
                        : !canDeleteEmployee(emp)
                          ? t('admin.cannotDeleteEmployee')
                          : undefined
                    }
                    className="text-red-700 hover:underline text-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:no-underline"
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
