import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { admin } from '../lib/api'
import type { Book } from '../lib/api'

export function AdminBooks() {
  const queryClient = useQueryClient()
  const [deleteId, setDeleteId] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['admin-books'],
    queryFn: async () => {
      const res = await admin.books.list()
      return res.data
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => admin.books.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      setDeleteId(null)
    },
  })

  const handleDelete = (book: Book) => {
    if (window.confirm(`Delete "${book.title}"?`)) {
      deleteMutation.mutate(book._id)
      setDeleteId(book._id)
    }
  }

  if (isLoading) return <div className="text-center py-12">Loading...</div>

  const paginated = data?.data
  const items = Array.isArray(paginated)
    ? paginated
    : (paginated?.data ?? [])

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-amber-900">Books</h1>
        <Link
          to="/admin/books/new"
          className="px-4 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          Add Book
        </Link>
      </div>
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">Title</th>
              <th className="px-4 py-2 text-left">ISBN</th>
              <th className="px-4 py-2 text-left">Price</th>
              <th className="px-4 py-2 text-left">Stock</th>
              <th className="px-4 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((book: Book) => (
              <tr key={book._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{book.title}</td>
                <td className="px-4 py-2">{book.isbn ?? '-'}</td>
                <td className="px-4 py-2">${book.price?.toFixed(2)}</td>
                <td className="px-4 py-2">{book.stock_quantity ?? 0}</td>
                <td className="px-4 py-2 text-right">
                  <Link
                    to={`/admin/books/${book._id}/edit`}
                    className="text-amber-700 hover:underline mr-3"
                  >
                    Edit
                  </Link>
                  <button
                    type="button"
                    onClick={() => handleDelete(book)}
                    disabled={deleteMutation.isPending && deleteId === book._id}
                    className="text-red-600 hover:underline disabled:opacity-50"
                  >
                    {deleteMutation.isPending && deleteId === book._id
                      ? 'Deleting...'
                      : 'Delete'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-8">
          No books yet.{' '}
          <Link to="/admin/books/new" className="text-amber-700 font-medium">
            Add your first book
          </Link>
        </p>
      )}
    </div>
  )
}
