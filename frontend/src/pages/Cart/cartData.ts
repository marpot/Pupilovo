export type CartProduct = {
  id: string
  name: string
  image: string
  price: number
  quantity: number
}

// Prices are stored in grosze to avoid floating-point rounding errors.
export const formatPrice = (amount: number) =>
  new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(amount / 100)

export const previewProducts: CartProduct[] = [
  {
    id: 'bowl',
    name: 'Miska spowalniająca Pupilovo',
    image: '/assets/hero.png',
    price: 5990,
    quantity: 1,
  },
  {
    id: 'mat',
    name: 'Mata węchowa Pupilovo',
    image: '/assets/hero.png',
    price: 7990,
    quantity: 2,
  },
]
