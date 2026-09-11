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
  const [isAdding, setIsAdding] = useState(false)

  const handleAddToCart = async () => {
    if (!available || isAdding) {
      return
    }

    try {
      setIsAdding(true)

      await addCartItem(id, 1)
    } catch (error) {
      console.error(
        'Nie udało się dodać produktu do koszyka:',
        error,
      )
    } finally {
      setIsAdding(false)
    }
  }

  return (
    <article className="product-card">
      <Link
        to={`/product/${slug}`}
        className="product-card__image"
      >
        <img src={image} alt={name} />
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

        <div className="product-card__footer">
          <span className="product-card__price">
            {price}
          </span>

          <button
            type="button"
            disabled={!available || isAdding}
            className="product-card__button"
            onClick={handleAddToCart}
          >
            {!available
              ? 'Niedostępny'
              : isAdding
                ? 'Dodawanie...'
                : 'Dodaj do koszyka'}
          </button>
        </div>
      </div>
    </article>
  )
}

export default ProductCard