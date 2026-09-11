export interface WooCommercePrice {
  price: string
  regular_price: string
  sale_price: string
  currency_code: string
  currency_symbol: string
  currency_minor_unit: number
}

export interface WooCommerceImage {
  id: number
  src: string
  thumbnail: string
  srcset: string
  sizes: string
  name: string
  alt: string
}

export interface WooCommerceProductCategory {
  id: number
  name: string
  slug: string
}

export interface WooCommerceProduct {
  id: number
  name: string
  slug: string
  short_description: string
  description: string
  prices: WooCommercePrice
  images: WooCommerceImage[]
  categories: WooCommerceProductCategory[]
  is_in_stock: boolean
}

export interface WooCommerceCategory {
  id: number
  name: string
  slug: string
  count: number
}

export interface Product {
  id: number
  slug: string
  name: string
  price: string
  image: string
  description: string
  category: string
  available: boolean
}

export interface ProductCategory {
  name: string
  value: string
}

export interface WooCommerceCartItemImage {
  id: number
  src: string
  thumbnail: string
  srcset: string
  sizes: string
  name: string
  alt: string
}

export interface WooCommerceCartItemPrices {
  price: string
  regular_price: string
  sale_price: string
  currency_code: string
  currency_symbol: string
  currency_minor_unit: number
}

export interface WooCommerceCartItem {
  key: string
  id: number
  quantity: number
  name: string
  images: WooCommerceCartItemImage[]
  prices: WooCommerceCartItemPrices
}

export interface WooCommerceCartTotals {
  total_items: string
  total_items_tax: string
  total_fees: string
  total_fees_tax: string
  total_discount: string
  total_discount_tax: string
  total_shipping: string
  total_shipping_tax: string
  total_price: string
  total_tax: string
  tax_lines: unknown[]
  currency_code: string
  currency_symbol: string
  currency_minor_unit: number
}

export interface WooCommerceCart {
  items: WooCommerceCartItem[]
  items_count: number
  items_weight: number
  coupons: unknown[]
  fees: unknown[]
  totals: WooCommerceCartTotals
}