import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { admin, type Category } from '../lib/api'

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return Array.isArray(d) ? d : []
}

export function AdminCategories() {
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ dewey_code: '', subject_title: '' })
  const [error, setError] = useState('')

  const { data } = useQuery({
    queryKey: ['admin-categories'],
    queryFn: async () => {
      const res = await admin.categories.list()
      return res.data
    },
  })

  const createMutation = useMutation({
    mutationFn: (d: { dewey_code: string; subject_title: string }) =>
      admin.categories.create(d),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
      setForm({ dewey_code: '', subject_title: '' })
      setShowForm(false)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? 'Failed to create')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.categories.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
    },
  })

  const items = extractList<Category>(data)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">Categories</h1>
        <button
          type="button"
          onClick={() => setShowForm(true)}
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          Add Category
        </button>
      </div>
      {showForm && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-2">New category</h2>
          <div className="flex gap-2 flex-wrap">
            <input
              type="text"
              value={form.dewey_code}
              onChange={(e) => setForm((p) => ({ ...p, dewey_code: e.target.value }))}
              placeholder="Dewey code"
              className="w-28 px-4 py-2 border border-stone-300 rounded-lg"
            />
            <input
              type="text"
              value={form.subject_title}
              onChange={(e) => setForm((p) => ({ ...p, subject_title: e.target.value }))}
              placeholder="Subject title"
              className="flex-1 min-w-[200px] px-4 py-2 border border-stone-300 rounded-lg"
            />
            <button
              type="button"
              onClick={() =>
                form.dewey_code &&
                form.subject_title &&
                createMutation.mutate(form)
              }
              disabled={
                createMutation.isPending ||
                !form.dewey_code ||
                !form.subject_title
              }
              className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
            >
              Create
            </button>
            <button
              type="button"
              onClick={() => {
                setShowForm(false)
                setForm({ dewey_code: '', subject_title: '' })
                setError('')
              }}
              className="px-4 py-2 border border-stone-300 rounded-lg"
            >
              Cancel
            </button>
          </div>
          {error && <p className="mt-2 text-red-600 text-sm">{error}</p>}
        </div>
      )}
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">Dewey code</th>
              <th className="px-4 py-2 text-left">Subject</th>
              <th className="px-4 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((c) => (
              <tr key={c._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{c.dewey_code}</td>
                <td className="px-4 py-2">{c.subject_title}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    onClick={() =>
                      window.confirm('Delete this category?') &&
                      deleteMutation.mutate(c._id)
                    }
                    className="text-red-600 hover:underline text-sm"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 && !showForm && (
        <p className="text-center text-stone-500 py-8">No categories. Add one above.</p>
      )}
    </div>
  )
}
