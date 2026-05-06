import { useMutation, useQueryClient, useQuery } from '@tanstack/react-query'
import { cart } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'

export function useAddToCart() {
  const { userType } = useAuth()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { t } = useTranslation()

  const addToCartMutation = useMutation({
    mutationFn: (bookId: string) => cart.addItem(bookId, 1),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  })

  const { data: cartData } = useQuery({
    queryKey: ['cart'],
    queryFn: async () => {
      const res = await cart.get()
      return res.data
    },
    enabled: userType === 'customer',
  })

  const cartBookIds = (cartData?.data?.items ?? []).map((item: { book_id: string }) => item.book_id)

  const handleAddToCart = userType === 'customer' ? (bookId: string) => {
    addToCartMutation.mutate(bookId, {
      onError: (err: any) => {
        if (err?.response?.status === 401) {
          navigate('/login')
          return
        }
        const message = err?.response?.data?.message ?? t('common.error')
        alert(message)
      },
    })
  } : undefined

  return {
    handleAddToCart,
    isAddingToCart: (bookId: string) => addToCartMutation.isPending && addToCartMutation.variables === bookId,
    isInCart: (bookId: string) => cartBookIds.includes(bookId),
  }
}
