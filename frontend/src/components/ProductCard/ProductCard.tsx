import '@/components/ProductCard/ProductCard.scss'

interface ProductCardProps {
  name: string
  price: string
  image: string
  description?: string
  available?: boolean
}

function ProductCard({
  name,
  price,
  image,
  description,
  available = true,
}: ProductCardProps) {
  return (
    <article className="product-card">
      <div className="product-card__image">
        <img src={image} alt={name} />
      </div>

      <div className="product-card__content">
        <h3 className="product-card__name">{name}</h3>

        {description && (
          <p className="product-card__description">{description}</p>
        )}

        <div className="product-card__footer">
          <span className="product-card__price">{price}</span>

          <button
            type="button"
            disabled={!available}
            className="product-card__button"
          >
            {available ? 'Dodaj do koszyka' : 'Niedostępny'}
          </button>
        </div>
      </div>
    </article>
  )
}

export default ProductCard