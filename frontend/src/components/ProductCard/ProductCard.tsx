import { useState } from 'react'
import { Link } from 'react-router-dom'

import { addCartItem } from '@/api/cart'

import '@/components/ProductCard/ProductCard.scss'

interface ProductCardProps {
  id: number
  slug: string
  name: string
  price: string
  image: string
  description?: string
  available?: boolean
}

function ProductCard({
  id,
  slug,
  name,
  price,
  image,
  description,
  available = true,
}: ProductCardProps) {
  const [imageLoaded, setImageLoaded] = useState(false)
  const [isAdding, setIsAdding] = useState(false)
  const [cartMessage, setCartMessage] = useState<string | null>(null)
  const [cartError, setCartError] = useState(false)

  const handleAddToCart = async () => {
    if (!available || isAdding) {
      return
    }

    try {
      setIsAdding(true)
      setCartMessage(null)
      setCartError(false)

      await addCartItem(id, 1)
      setCartMessage('Dodano do koszyka.')
    } catch (error) {
      console.error('Nie udało się dodać produktu do koszyka:', error)
      setCartError(true)
      setCartMessage('Nie udało się dodać produktu. Spróbuj ponownie.')
    } finally {
      setIsAdding(false)
    }
  }

  return (
    <article className={`product-card${available ? '' : ' product-card--unavailable'}`}>
      <Link
        to={`/product/${slug}`}
        className={`product-card__image${imageLoaded ? ' is-loaded' : ''}`}
        aria-label={`Zobacz produkt: ${name}`}
      >
        <span className="product-card__image-loader" aria-hidden="true" />
        <img src={image} alt={name} loading="lazy" onLoad={() => setImageLoaded(true)} />
      </Link>

      <div className="product-card__content">
        <h3 className="product-card__name">
          <Link to={`/product/${slug}`}>
            {name}
          </Link>
        </h3>

        {description && (
          <p className="product-card__description">
            {description}
          </p>
        )}

        <p className={`product-card__availability${available ? '' : ' product-card__availability--unavailable'}`}>
          <span aria-hidden="true" />
          {available ? 'Dostępny' : 'Aktualnie niedostępny'}
        </p>

        <div className="product-card__footer">
          <span className="product-card__price">
            {price}
          </span>

          <button
            type="button"
            disabled={!available || isAdding}
            className="product-card__button"
            onClick={handleAddToCart}
            aria-busy={isAdding}
          >
            {!available
              ? 'Niedostępny'
              : isAdding
                ? 'Dodawanie...'
                : 'Dodaj do koszyka'}
          </button>
        </div>
        {cartMessage && (
          <p className={`product-card__cart-message${cartError ? ' product-card__cart-message--error' : ''}`} role={cartError ? 'alert' : 'status'}>
            {cartMessage}
          </p>
        )}
      </div>
    </article>
  )
}

export function ProductCardSkeleton() {
  return (
    <div className="product-card product-card--skeleton" aria-hidden="true">
      <span className="product-card__skeleton-image" />
      <div className="product-card__content">
        <span className="product-card__skeleton-line product-card__skeleton-line--title" />
        <span className="product-card__skeleton-line" />
        <span className="product-card__skeleton-line product-card__skeleton-line--short" />
        <span className="product-card__skeleton-button" />
      </div>
    </div>
  )
}

export default ProductCard
