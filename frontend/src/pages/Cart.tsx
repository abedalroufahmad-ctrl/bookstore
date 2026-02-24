import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { cart } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

export function CartPage() {
  const { userType } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['cart'],
    queryFn: async () => {
      const res = await cart.get()
      return res.data
    },
    enabled: userType === 'customer',
  })

  const removeItem = useMutation({
    mutationFn: (bookId: string) => cart.removeItem(bookId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  const updateQty = useMutation({
    mutationFn: ({ bookId, qty }: { bookId: string; qty: number }) =>
      cart.updateItem(bookId, qty),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  if (userType !== 'customer') {
    navigate('/login')
    return null
  }

  if (isLoading) return <div className="text-center py-12">Loading...</div>

  const items = data?.data?.items ?? []
  const total = data?.data?.total ?? 0

  if (items.length === 0) {
    return (
      <div className="text-center py-12">
        <p className="text-stone-600 mb-4">Your cart is empty</p>
        <Link to="/" className="text-amber-700 font-medium">Browse books</Link>
      </div>
    )
  }

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">Your Cart</h1>
      <div className="space-y-4">
        {items.map((item: { book_id: string; quantity: number; price: number; book?: { title: string } }) => (
          <div
            key={item.book_id}
            className="flex items-center justify-between bg-white p-4 rounded-lg border border-stone-200"
          >
            <div>
              <p className="font-medium">{item.book?.title ?? 'Book'}</p>
              <p className="text-sm text-stone-500">${item.price?.toFixed(2)} × {item.quantity}</p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => updateQty.mutate({ bookId: item.book_id, qty: item.quantity - 1 })}
                disabled={item.quantity <= 1}
                className="w-8 h-8 rounded bg-stone-200 hover:bg-stone-300 disabled:opacity-50"
              >
                −
              </button>
              <span>{item.quantity}</span>
              <button
                onClick={() => updateQty.mutate({ bookId: item.book_id, qty: item.quantity + 1 })}
                className="w-8 h-8 rounded bg-stone-200 hover:bg-stone-300"
              >
                +
              </button>
              <button
                onClick={() => removeItem.mutate(item.book_id)}
                className="ml-2 text-red-600 text-sm hover:underline"
              >
                Remove
              </button>
            </div>
          </div>
        ))}
      </div>
      <div className="mt-6 flex justify-between items-center">
        <p className="text-lg font-bold">Total: ${total.toFixed(2)}</p>
        <Link
          to="/checkout"
          className="px-6 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800"
        >
          Checkout
        </Link>
      </div>
    </div>
  )
}
