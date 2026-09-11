import { useState } from "react";

import { addCartItem } from "@/api/cart";

import "@/components/ProductCard/ProductCard.scss";

interface ProductCardProps {
  id: number;
  name: string;
  price: string;
  image: string;
  description?: string;
  available?: boolean;
}

function ProductCard({
  id,
  name,
  price,
  image,
  description,
  available = true,
}: ProductCardProps) {
  const [isAdding, setIsAdding] = useState(false);

  const handleAddToCart = async () => {
    if (!available || isAdding) {
      return;
    }

    try {
      setIsAdding(true);

      await addCartItem(id, 1);
    } catch (error) {
      console.error("Nie udało się dodać produktu do koszyka:", error);
    } finally {
      setIsAdding(false);
    }
  };

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
            disabled={!available || isAdding}
            className="product-card__button"
            onClick={handleAddToCart}
          >
            {!available
              ? "Niedostępny"
              : isAdding
                ? "Dodawanie..."
                : "Dodaj do koszyka"}
          </button>
        </div>
      </div>
    </article>
  );
}

export default ProductCard;