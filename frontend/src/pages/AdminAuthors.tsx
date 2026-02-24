import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { admin, type Author } from '../lib/api'

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return Array.isArray(d) ? d : []
}

export function AdminAuthors() {
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [name, setName] = useState('')
  const [error, setError] = useState('')

  const { data } = useQuery({
    queryKey: ['admin-authors'],
    queryFn: async () => {
      const res = await admin.authors.list()
      return res.data
    },
  })

  const createMutation = useMutation({
    mutationFn: (n: string) => admin.authors.create({ name: n }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-authors'] })
      setName('')
      setShowForm(false)
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? 'Failed to create')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.authors.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-authors'] })
    },
  })

  const items = extractList<Author>(data)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">Authors</h1>
        <button
          type="button"
          onClick={() => setShowForm(true)}
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          Add Author
        </button>
      </div>
      {showForm && (
        <div className="mb-6 p-4 bg-stone-50 rounded-lg border border-stone-200">
          <h2 className="font-semibold mb-2">New author</h2>
          <div className="flex gap-2">
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Author name"
              className="flex-1 px-4 py-2 border border-stone-300 rounded-lg"
            />
            <button
              type="button"
              onClick={() => name.trim() && createMutation.mutate(name.trim())}
              disabled={createMutation.isPending || !name.trim()}
              className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
            >
              Create
            </button>
            <button
              type="button"
              onClick={() => { setShowForm(false); setName(''); setError('') }}
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
              <th className="px-4 py-2 text-left">Name</th>
              <th className="px-4 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((a) => (
              <tr key={a._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{a.name}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    onClick={() =>
                      window.confirm('Delete this author?') && deleteMutation.mutate(a._id)
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
        <p className="text-center text-stone-500 py-8">No authors. Add one above.</p>
      )}
    </div>
  )
}
