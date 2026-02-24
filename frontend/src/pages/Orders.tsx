import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { orders } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

export function Orders() {
  const { userType } = useAuth()

  const { data, isLoading } = useQuery({
    queryKey: ['orders'],
    queryFn: async () => {
      const res = await orders.list()
      return res.data
    },
    enabled: userType === 'customer',
  })

  if (userType !== 'customer') return null

  if (isLoading) return <div className="text-center py-12">Loading...</div>

  const items = data?.data?.data ?? data?.data ?? []

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">My Orders</h1>
      <div className="space-y-4">
        {items.map((order: { _id: string; status: string; total: number; created_at?: string }) => (
          <div
            key={order._id}
            className="bg-white p-4 rounded-lg border border-stone-200 flex justify-between items-center"
          >
            <div>
              <p className="font-medium">Order #{order._id?.slice(-6)}</p>
              <p className="text-sm text-stone-500">${order.total?.toFixed(2)} · {order.status}</p>
            </div>
            <Link
              to={`/orders/${order._id}`}
              className="text-amber-700 font-medium text-sm"
            >
              View →
            </Link>
          </div>
        ))}
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-12">No orders yet</p>
      )}
    </div>
  )
}
