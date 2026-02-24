import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { cart, orders } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'

export function Checkout() {
  const [address, setAddress] = useState('')
  const [city, setCity] = useState('')
  const [country, setCountry] = useState('')
  const [postalCode, setPostalCode] = useState('')
  const { userType } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const checkout = useMutation({
    mutationFn: () =>
      orders.checkout(
        { address, city, country, postal_code: postalCode || undefined },
        undefined
      ),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cart'] })
      navigate('/orders')
    },
  })

  if (userType !== 'customer') {
    navigate('/login')
    return null
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    checkout.mutate()
  }

  return (
    <div className="max-w-md mx-auto">
      <h1 className="text-2xl font-bold text-amber-900 mb-6">Checkout</h1>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">Address</label>
          <input
            type="text"
            value={address}
            onChange={(e) => setAddress(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">City</label>
          <input
            type="text"
            value={city}
            onChange={(e) => setCity(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">Country</label>
          <input
            type="text"
            value={country}
            onChange={(e) => setCountry(e.target.value)}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">Postal Code</label>
          <input
            type="text"
            value={postalCode}
            onChange={(e) => setPostalCode(e.target.value)}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg"
          />
        </div>
        {checkout.error && (
          <p className="text-red-600 text-sm">
            {(checkout.error as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Checkout failed'}
          </p>
        )}
        <button
          type="submit"
          disabled={checkout.isPending}
          className="w-full py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
        >
          {checkout.isPending ? 'Place Order...' : 'Place Order'}
        </button>
      </form>
    </div>
  )
}
