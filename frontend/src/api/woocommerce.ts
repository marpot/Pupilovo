import type {
  Product,
  ProductCategory,
  WooCommerceCategory,
  WooCommerceProduct,
} from '@/types/woocommerce'

const STORE_API_URL = '/wp-json/wc/store/v1'

const request = async <T>(
  endpoint: string,
  signal?: AbortSignal,
): Promise<T> => {
  const response = await fetch(`${STORE_API_URL}${endpoint}`, {
    signal,
  })

  if (!response.ok) {
    throw new Error(
      `WooCommerce Store API error: ${response.status} ${response.statusText}`,
    )
  }

  return response.json() as Promise<T>
}

const formatPrice = (product: WooCommerceProduct) => {
  const {
    price,
    currency_code: currencyCode,
    currency_minor_unit: minorUnit,
  } = product.prices

  const value = Number(price) / 10 ** minorUnit

  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: currencyCode,
  }).format(value)
}

const stripHtml = (value: string) =>
  value
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

const mapProduct = (
  product: WooCommerceProduct,
): Product => ({
  id: product.id,
  slug: product.slug,
  name: product.name,
  price: formatPrice(product),
  priceValue: Number(product.prices.price) / 10 ** product.prices.currency_minor_unit,
  image: product.images[0]?.src || '/assets/hero.png',
  images: product.images.map((image) => ({ src: image.src, alt: image.alt || product.name })),
  description: stripHtml(
    product.short_description || product.description,
  ),
  category: product.categories[0]?.slug || '',
  available: product.is_in_stock,
})

export const getProducts = async (
  signal?: AbortSignal,
): Promise<Product[]> => {
  const products = await request<WooCommerceProduct[]>(
    '/products?per_page=100',
    signal,
  )

  return products.map(mapProduct)
}

export const getFeaturedProducts = async (
  signal?: AbortSignal,
): Promise<Product[]> => {
  const products = await request<WooCommerceProduct[]>(
    '/products?featured=true&per_page=4',
    signal,
  )

  return products.map(mapProduct)
}

export const getProductBySlug = async (
  slug: string,
  signal?: AbortSignal,
): Promise<Product> => {
  const product = await request<WooCommerceProduct>(
    `/products/${encodeURIComponent(slug)}`,
    signal,
  )

  return mapProduct(product)
}

export const getProductCategories = async (
  signal?: AbortSignal,
): Promise<ProductCategory[]> => {
  const categories = await request<WooCommerceCategory[]>(
    '/products/categories?per_page=100',
    signal,
  )

  return categories
    .filter(
      (category) =>
        category.slug.toLocaleLowerCase('en-US') !==
          'uncategorized' &&
        category.name.trim().toLocaleLowerCase('en-US') !==
          'uncategorized',
    )
    .map((category) => ({
      id: category.id,
      name: category.name,
      value: category.slug,
      count: category.count,
      parent: category.parent,
      image: category.image?.src || null,
    }))
}
