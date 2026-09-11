import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { addCartItem } from '@/api/cart'
import { getProductBySlug } from '@/api/woocommerce'
import type { Product } from '@/types/woocommerce'

import '@/pages/ProductDetails/ProductDetails.scss'

function ProductDetails() {
  const { slug } = useParams<{ slug: string }>()

  const [product, setProduct] = useState<Product | null>(null)
  const [quantity, setQuantity] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [isAdding, setIsAdding] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!slug) {
      setError('Nie znaleziono produktu.')
      setIsLoading(false)
      return
    }

    const controller = new AbortController()

    const loadProduct = async () => {
      try {
        setIsLoading(true)
        setError(null)

        const productData = await getProductBySlug(
          slug,
          controller.signal,
        )

        setProduct(productData)
      } catch (productError) {
        if (
          productError instanceof DOMException &&
          productError.name === 'AbortError'
        ) {
          return
        }

        console.error(productError)
        setError('Nie udało się pobrać produktu.')
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false)
        }
      }
    }

    void loadProduct()

    return () => controller.abort()
  }, [slug])

  const decreaseQuantity = () => {
    setQuantity((current) => Math.max(1, current - 1))
  }

  const increaseQuantity = () => {
    setQuantity((current) => Math.min(99, current + 1))
  }

  const handleAddToCart = async () => {
    if (!product || !product.available || isAdding) {
      return
    }

    try {
      setIsAdding(true)
      setMessage(null)

      await addCartItem(product.id, quantity)

      setMessage('Produkt został dodany do koszyka.')
    } catch (cartError) {
      console.error(cartError)

      setMessage('Nie udało się dodać produktu do koszyka.')
    } finally {
      setIsAdding(false)
    }
  }

  if (isLoading) {
    return (
      <main className="product-details">
        <div className="product-details__container">
          <p role="status">Ładowanie produktu...</p>
        </div>
      </main>
    )
  }

  if (error || !product) {
    return (
      <main className="product-details">
        <div className="product-details__container">
          <h1>Nie znaleziono produktu</h1>

          <p>{error}</p>

          <Link to="/shop">
            Wróć do sklepu
          </Link>
        </div>
      </main>
    )
  }

  return (
    <main className="product-details">
      <div className="product-details__container">
        <Link
          to="/shop"
          className="product-details__back"
        >
          ← Wróć do sklepu
        </Link>

        <div className="product-details__layout">
          <div className="product-details__image">
            <img
              src={product.image}
              alt={product.name}
            />
          </div>

          <div className="product-details__content">
            <h1>{product.name}</h1>

            <p className="product-details__price">
              {product.price}
            </p>

            <p
              className={
                product.available
                  ? 'product-details__stock product-details__stock--available'
                  : 'product-details__stock product-details__stock--unavailable'
              }
            >
              {product.available
                ? 'Produkt dostępny'
                : 'Produkt niedostępny'}
            </p>

            {product.description && (
              <p className="product-details__description">
                {product.description}
              </p>
            )}

            {product.available && (
              <div className="product-details__purchase">
                <div
                  className="product-details__quantity"
                  aria-label="Liczba produktów"
                >
                  <button
                    type="button"
                    onClick={decreaseQuantity}
                    disabled={quantity <= 1 || isAdding}
                    aria-label="Zmniejsz liczbę"
                  >
                    −
                  </button>

                  <span>{quantity}</span>

                  <button
                    type="button"
                    onClick={increaseQuantity}
                    disabled={quantity >= 99 || isAdding}
                    aria-label="Zwiększ liczbę"
                  >
                    +
                  </button>
                </div>

                <button
                  type="button"
                  className="product-details__add"
                  onClick={handleAddToCart}
                  disabled={isAdding}
                >
                  {isAdding
                    ? 'Dodawanie...'
                    : 'Dodaj do koszyka'}
                </button>
              </div>
            )}

            {message && (
              <p
                className="product-details__message"
                role="status"
              >
                {message}
              </p>
            )}
          </div>
        </div>
      </div>
    </main>
  )
}

export default ProductDetails