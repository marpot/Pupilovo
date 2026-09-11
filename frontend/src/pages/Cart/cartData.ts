import type { WooCommerceCartItem } from '@/types/woocommerce'

export type CartProduct = {
  id: string
  productId: number
  name: string
  image: string
  price: number
  quantity: number
}

export const formatPrice = (amount: number) =>
  new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
  }).format(amount / 100)

export const mapCartProduct = (
  item: WooCommerceCartItem,
): CartProduct => ({
  id: item.key,
  productId: item.id,
  name: item.name,
  image: item.images[0]?.src || '/assets/hero.png',
  price: Number(item.prices.price),
  quantity: item.quantity,
})