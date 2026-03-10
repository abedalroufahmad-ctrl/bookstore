import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Country, type CountryFormData, type CountryCity } from '../lib/api'
import { Pagination } from '../components/Pagination'

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return []
}

const emptyForm: CountryFormData = {
  name: '',
  code: '',
  currency_code: '',
  currency_name: '',
  cities: [],
}

export function AdminCountries() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [form, setForm] = useState<CountryFormData>(emptyForm)
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['admin-countries', page],
    queryFn: async () => {
      const res = await admin.countries.list({ page, per_page: 20 })
      return res.data
    },
  })

  const createMutation = useMutation({
    mutationFn: (payload: CountryFormData) => admin.countries.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-countries'] })
      setForm(emptyForm)
      setShowForm(false)
      setError('')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data: payload }: { id: string; data: CountryFormData }) =>
      admin.countries.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-countries'] })
      setEditingId(null)
      setForm(emptyForm)
      setShowForm(false)
      setError('')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.countries.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-countries'] })
    },
  })

  const syncFromNetworkMutation = useMutation({
    mutationFn: (dryRun?: boolean) => admin.countries.syncFromNetwork(dryRun),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['admin-countries'] })
      const msg = res.data?.data?.output ?? res.data?.data?.message ?? 'Sync completed.'
      alert(msg)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? 'Sync failed.')
    },
  })

  const syncCitiesMutation = useMutation({
    mutationFn: (opts?: { dry_run?: boolean; limit?: number }) =>
      admin.countries.syncCitiesFromDataset(opts),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['admin-countries'] })
      const msg = res.data?.data?.output ?? res.data?.data?.message ?? 'Cities sync completed.'
      alert(msg)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? 'Cities sync failed.')
    },
  })

  const paginated = data?.data
  const items: Country[] = paginated?.data ?? extractList<Country>(data)
  const meta = paginated && 'current_page' in paginated ? paginated : null

  const startCreate = () => {
    setEditingId(null)
    setForm(emptyForm)
    setError('')
    setShowForm(true)
  }

  const startEdit = async (c: Country) => {
    setError('')
    try {
      const res = await admin.countries.get(c._id)
      const full = res.data.data as Country
      setEditingId(full._id)
      setForm({
        name: full.name,
        code: full.code ?? '',
        currency_code: full.currency_code,
        currency_name: full.currency_name ?? '',
        cities: (full.cities ?? []).map((city) => ({ id: city.id, name: city.name })),
      })
      setShowForm(true)
    } catch {
      setError(t('admin.failedUpdate'))
    }
  }

  const handleCityChange = (index: number, updates: Partial<CountryCity>) => {
    setForm((prev) => ({
      ...prev,
      cities: prev.cities.map((c, i) => (i === index ? { ...c, ...updates } : c)),
    }))
  }

  const addCityRow = () => {
    setForm((prev) => ({ ...prev, cities: [...prev.cities, { name: '' }] }))
  }

  const removeCityRow = (index: number) => {
    setForm((prev) => ({ ...prev, cities: prev.cities.filter((_, i) => i !== index) }))
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    const payload: CountryFormData = {
      ...form,
      cities: form.cities.filter((c) => c.name.trim().length > 0),
    }
    if (!payload.name.trim() || !payload.currency_code.trim()) {
      setError(t('admin.fillRequired'))
      return
    }
    if (editingId) {
      updateMutation.mutate({ id: editingId, data: payload })
    } else {
      createMutation.mutate(payload)
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">{t('admin.countries')}</h1>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => syncFromNetworkMutation.mutate(false)}
            disabled={syncFromNetworkMutation.isPending}
            className="px-4 py-2 bg-stone-600 text-white rounded-lg hover:bg-stone-700 disabled:opacity-50"
          >
            {syncFromNetworkMutation.isPending ? t('common.loading') : t('admin.syncFromNetwork')}
          </button>
          <button
            type="button"
            onClick={() => syncCitiesMutation.mutate()}
            disabled={syncCitiesMutation.isPending}
            className="px-4 py-2 bg-emerald-700 text-white rounded-lg hover:bg-emerald-800 disabled:opacity-50"
          >
            {syncCitiesMutation.isPending ? t('common.loading') : t('admin.getMoreCities')}
          </button>
          <button
            type="button"
            onClick={startCreate}
            className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
          >
            {t('admin.addCountry')}
          </button>
        </div>
      </div>

      {error && (
        <div className="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
          {error}
        </div>
      )}

      {isLoading ? (
        <div className="text-center py-12">{t('common.loading')}</div>
      ) : (
        <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
          <table className="w-full">
            <thead className="bg-stone-100">
              <tr>
                <th className="px-4 py-2 text-left">{t('admin.name')}</th>
                <th className="px-4 py-2 text-left">{t('admin.code')}</th>
                <th className="px-4 py-2 text-left">{t('admin.currency')}</th>
                <th className="px-4 py-2 text-left">{t('admin.cities')}</th>
                <th className="px-4 py-2 text-right">{t('admin.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {items.map((c) => (
                <tr key={c._id} className="border-t border-stone-200">
                  <td className="px-4 py-2 font-medium">{c.name}</td>
                  <td className="px-4 py-2 text-sm text-stone-600">{c.code || '-'}</td>
                  <td className="px-4 py-2 text-sm text-stone-800">
                    {c.currency_code}
                    {c.currency_name ? ` – ${c.currency_name}` : ''}
                  </td>
                  <td className="px-4 py-2 text-sm text-stone-600">
                    {c.cities?.length ?? 0}
                  </td>
                  <td className="px-4 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => startEdit(c)}
                      className="text-amber-700 hover:underline text-sm mr-3"
                    >
                      {t('admin.edit')}
                    </button>
                    <button
                      type="button"
                      onClick={() =>
                        window.confirm(t('admin.deleteCountryConfirm')) &&
                        deleteMutation.mutate(c._id)
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
          {items.length === 0 && (
            <p className="text-center text-stone-500 py-8">{t('admin.noCountries')}</p>
          )}
        </div>
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

      {showForm && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto p-6">
            <h2 className="text-xl font-semibold text-amber-900 mb-4">
              {editingId ? t('admin.editCountry') : t('admin.newCountry')}
            </h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">
                  {t('admin.name')}
                </label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                  required
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">
                    {t('admin.code')}
                  </label>
                  <input
                    type="text"
                    value={form.code}
                    onChange={(e) => setForm((p) => ({ ...p, code: e.target.value }))}
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-stone-700 mb-1">
                    {t('admin.currencyCode')}
                  </label>
                  <input
                    type="text"
                    value={form.currency_code}
                    onChange={(e) => setForm((p) => ({ ...p, currency_code: e.target.value }))}
                    required
                    className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">
                  {t('admin.currencyName')}
                </label>
                <input
                  type="text"
                  value={form.currency_name}
                  onChange={(e) => setForm((p) => ({ ...p, currency_name: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">
                  {t('admin.cities')}
                </label>
                <div className="space-y-2">
                  {form.cities.map((city, index) => (
                    <div key={index} className="flex gap-2 items-center">
                      <input
                        type="text"
                        value={city.name}
                        onChange={(e) =>
                          handleCityChange(index, { name: e.target.value })
                        }
                        placeholder={t('admin.city') || 'City'}
                        className="flex-1 px-3 py-2 border border-stone-300 rounded-lg text-sm"
                      />
                      <button
                        type="button"
                        onClick={() => removeCityRow(index)}
                        className="text-red-600 hover:underline text-xs"
                      >
                        {t('admin.delete')}
                      </button>
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={addCityRow}
                    className="text-sm text-amber-700 hover:underline"
                  >
                    + {t('admin.addCity')}
                  </button>
                </div>
              </div>

              <div className="flex justify-end gap-2 mt-4">
                <button
                  type="button"
                  onClick={() => {
                    setShowForm(false)
                    setEditingId(null)
                    setForm(emptyForm)
                    setError('')
                  }}
                  className="px-4 py-2 border border-stone-300 rounded-lg"
                >
                  {t('admin.cancel')}
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending || updateMutation.isPending}
                  className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
                >
                  {editingId
                    ? updateMutation.isPending
                      ? t('common.saving')
                      : t('admin.update')
                    : createMutation.isPending
                      ? t('common.saving')
                      : t('admin.create')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}

