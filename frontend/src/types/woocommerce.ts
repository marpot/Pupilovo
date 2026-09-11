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