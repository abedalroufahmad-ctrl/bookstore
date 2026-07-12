import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useParams, Link } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { api } from '../lib/api'

interface PublisherSettingsData {
  support_email?: string
  support_phone?: string
  return_policy?: string
  default_discount?: number
}

export function PublisherSettings() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const queryClient = useQueryClient()
  const { user, userType } = useAuth()
  
  const isPublisherManager = userType === 'employee' && user?.role === 'publisher_manager'
  const managedPublisherId = isPublisherManager ? user?.publisher_id : undefined

  // If publisher manager, force their own ID. Otherwise (admin), use URL param.
  const targetId = isPublisherManager ? managedPublisherId : id

  const [form, setForm] = useState<PublisherSettingsData>({
    support_email: '',
    support_phone: '',
    return_policy: '',
    default_discount: 0,
  })
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['publisher-settings', targetId],
    queryFn: async () => {
      const res = await api.get(`/admin/publishers/${targetId}/settings`)
      return res.data
    },
    enabled: !!targetId,
  })

  useEffect(() => {
    if (data?.data) {
      setForm({
        support_email: data.data.support_email ?? '',
        support_phone: data.data.support_phone ?? '',
        return_policy: data.data.return_policy ?? '',
        default_discount: Number(data.data.default_discount) || 0,
      })
    }
  }, [data])

  const updateMutation = useMutation({
    mutationFn: (payload: PublisherSettingsData) =>
      api.put(`/admin/publishers/${targetId}/settings`, payload),
    onSuccess: () => {
      setMessage(t('admin.settingsSaved', 'Settings saved successfully!'))
      setError('')
      queryClient.invalidateQueries({ queryKey: ['publisher-settings', targetId] })
      setTimeout(() => setMessage(''), 3000)
    },
    onError: (err: unknown) => {
      const error = err as { response?: { data?: { message?: string } } }
      setError(error?.response?.data?.message || t('common.error'))
      setMessage('')
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    updateMutation.mutate({
      ...form,
      default_discount: Number(form.default_discount),
    })
  }

  if (!targetId) {
    return (
      <div className="text-center py-12 text-stone-500">
        No publisher selected or you do not have permission.
      </div>
    )
  }

  if (isLoading) {
    return <div className="text-center py-12">{t('common.loading')}</div>
  }

  return (
    <div className="max-w-3xl mx-auto">
      <div className="flex items-center gap-4 mb-6">
        <Link to={isPublisherManager ? "/admin" : "/admin/publishers"} className="text-amber-700 hover:underline text-sm">
          ← {isPublisherManager ? t('admin.dashboard') : t('admin.publishers')}
        </Link>
      </div>

      <h1 className="text-2xl font-bold text-amber-900 mb-6">
        {t('admin.publisherSettings', 'Publisher Settings')}
      </h1>

      {message && (
        <div className="mb-6 p-4 bg-green-50 text-green-800 rounded-lg border border-green-200">
          {message}
        </div>
      )}
      {error && (
        <div className="mb-6 p-4 bg-red-50 text-red-800 rounded-lg border border-red-200">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
        <div className="p-6 space-y-6">
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-2">
                {t('admin.supportEmail', 'Support Email')}
              </label>
              <input
                type="email"
                value={form.support_email}
                onChange={(e) => setForm({ ...form, support_email: e.target.value })}
                className="w-full px-4 py-2 bg-stone-50 border border-stone-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-2">
                {t('admin.supportPhone', 'Support Phone')}
              </label>
              <input
                type="text"
                value={form.support_phone}
                onChange={(e) => setForm({ ...form, support_phone: e.target.value })}
                className="w-full px-4 py-2 bg-stone-50 border border-stone-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-stone-700 mb-2">
              {t('admin.defaultDiscount', 'Default Discount (%)')}
            </label>
            <input
              type="number"
              min="0"
              max="100"
              value={form.default_discount}
              onChange={(e) => setForm({ ...form, default_discount: Number(e.target.value) })}
              className="w-full max-w-[200px] px-4 py-2 bg-stone-50 border border-stone-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
            <p className="text-sm text-stone-500 mt-1">
              {t('admin.defaultDiscountHint', 'This discount will be applied to all your books unless overridden.')}
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-stone-700 mb-2">
              {t('admin.returnPolicy', 'Return Policy')}
            </label>
            <textarea
              rows={4}
              value={form.return_policy}
              onChange={(e) => setForm({ ...form, return_policy: e.target.value })}
              className="w-full px-4 py-2 bg-stone-50 border border-stone-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              placeholder={t('admin.returnPolicyPlaceholder', 'Describe your return policy...')}
            />
          </div>

        </div>
        
        <div className="bg-stone-50 px-6 py-4 border-t border-stone-200 flex justify-end">
          <button
            type="submit"
            disabled={updateMutation.isPending}
            className="px-6 py-2.5 bg-amber-600 text-white font-medium rounded-lg hover:bg-amber-700 transition-colors disabled:opacity-50"
          >
            {updateMutation.isPending ? t('common.saving', 'Saving...') : t('common.save', 'Save Changes')}
          </button>
        </div>
      </form>
    </div>
  )
}
