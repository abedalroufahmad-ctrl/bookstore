import { useState, useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Author } from '../lib/api'
import { resolveCoverUrl } from '../lib/utils'

const emptyForm = {
  name: '',
  biography: '',
  date_of_birth: '',
  date_of_death: '',
  photo: '',
}

export function AdminAuthorForm() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const isEdit = Boolean(id)

  const [form, setForm] = useState(emptyForm)
  const [error, setError] = useState('')
  const [photoUploading, setPhotoUploading] = useState(false)

  const { data: authorData } = useQuery({
    queryKey: ['admin-author', id],
    queryFn: async () => {
      const res = await admin.authors.get(id!)
      return res.data
    },
    enabled: isEdit,
  })

  useEffect(() => {
    if (authorData?.data) {
      const a = authorData.data as Author
      setForm({
        name: a.name ?? '',
        biography: a.biography ?? '',
        date_of_birth: a.date_of_birth ?? '',
        date_of_death: a.date_of_death ?? '',
        photo: a.photo ?? '',
      })
    }
  }, [authorData])

  const createMutation = useMutation({
    mutationFn: (data: typeof form) =>
      admin.authors.create({
        name: data.name.trim(),
        biography: data.biography.trim() || undefined,
        date_of_birth: data.date_of_birth || undefined,
        date_of_death: data.date_of_death || undefined,
        photo: data.photo || undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-authors'] })
      navigate('/admin/authors')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: typeof form) =>
      admin.authors.update(id!, {
        name: data.name.trim(),
        biography: data.biography.trim() || undefined,
        date_of_birth: data.date_of_birth || undefined,
        date_of_death: data.date_of_death || undefined,
        photo: data.photo || undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-authors'] })
      queryClient.invalidateQueries({ queryKey: ['admin-author', id] })
      navigate('/admin/authors')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    if (!form.name.trim()) {
      setError(t('admin.fillRequired'))
      return
    }
    if (isEdit) {
      updateMutation.mutate(form)
    } else {
      createMutation.mutate(form)
    }
  }

  const loading = createMutation.isPending || updateMutation.isPending

  return (
    <div>
      <div className="mb-6">
        <button
          type="button"
          onClick={() => navigate('/admin/authors')}
          className="text-amber-700 hover:underline text-sm"
        >
          ← {t('admin.backToAuthors')}
        </button>
      </div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">
        {isEdit ? t('admin.editAuthor') : t('admin.newAuthor')}
      </h1>
      <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
        <div className="flex flex-col sm:flex-row gap-6">
          <div className="flex-shrink-0">
            <label className="block text-sm font-medium text-stone-700 mb-2">{t('admin.authorPhoto')}</label>
            <div className="w-32 h-32 rounded-lg border-2 border-dashed border-stone-300 flex items-center justify-center overflow-hidden bg-stone-50">
              {form.photo ? (
                <img
                  src={resolveCoverUrl(form.photo)}
                  alt={form.name || 'Author'}
                  className="w-full h-full object-cover"
                />
              ) : (
                <span className="text-4xl text-stone-400">👤</span>
              )}
            </div>
            <input
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
              onChange={async (e) => {
                const file = e.target.files?.[0]
                if (!file) return
                setPhotoUploading(true)
                try {
                  const res = await admin.uploadAuthorPhoto(file)
                  const data = res.data?.data
                  if (data?.photo) {
                    setForm((p) => ({ ...p, photo: data.photo }))
                  }
                } catch {
                  setError(t('admin.failedUpload'))
                } finally {
                  setPhotoUploading(false)
                  e.target.value = ''
                }
              }}
              disabled={photoUploading}
              className="mt-2 w-full text-sm file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-100 file:text-amber-900 file:font-medium hover:file:bg-amber-200"
            />
            {photoUploading && (
              <p className="mt-1 text-sm text-amber-700">{t('admin.uploading')}</p>
            )}
            {form.photo && (
              <button
                type="button"
                onClick={() => setForm((p) => ({ ...p, photo: '' }))}
                className="mt-2 text-sm text-red-600 hover:underline"
              >
                {t('admin.removePhoto')}
              </button>
            )}
          </div>
          <div className="flex-1 space-y-4">
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.name')} *</label>
              <input
                type="text"
                value={form.name}
                onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                placeholder={t('admin.authorName')}
                required
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.biography')}</label>
              <textarea
                value={form.biography}
                onChange={(e) => setForm((p) => ({ ...p, biography: e.target.value }))}
                placeholder={t('admin.biographyPlaceholder')}
                rows={4}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.dateOfBirth')}</label>
                <input
                  type="date"
                  value={form.date_of_birth}
                  onChange={(e) => setForm((p) => ({ ...p, date_of_birth: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-stone-700 mb-1">{t('admin.dateOfDeath')}</label>
                <input
                  type="date"
                  value={form.date_of_death}
                  onChange={(e) => setForm((p) => ({ ...p, date_of_death: e.target.value }))}
                  className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
                />
              </div>
            </div>
          </div>
        </div>
        {error && <p className="text-red-600 text-sm">{error}</p>}
        <div className="flex gap-3">
          <button
            type="submit"
            disabled={loading || !form.name.trim()}
            className="px-6 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
          >
            {loading ? t('common.saving') : isEdit ? t('admin.update') : t('admin.create')}
          </button>
          <button
            type="button"
            onClick={() => navigate('/admin/authors')}
            className="px-6 py-2 border border-stone-300 rounded-lg hover:bg-stone-50"
          >
            {t('admin.cancel')}
          </button>
        </div>
      </form>
    </div>
  )
}
