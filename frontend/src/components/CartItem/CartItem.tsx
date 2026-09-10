import type { CartProduct } from '@/pages/Cart/cartData'
import { formatPrice } from '@/pages/Cart/cartData'

type CartItemProps = {
  product: CartProduct
  onQuantityChange: (id: string, quantity: number) => void
  onRemove: (id: string) => void
}

function CartItem({ product, onQuantityChange, onRemove }: CartItemProps) {
  return (
    <li className="cart-item">
      <img className="cart-item__image" src={product.image} alt="" />
      <div className="cart-item__details">
        <h2>{product.name}</h2>
        <p>{formatPrice(product.price)} / szt.</p>
        <button className="cart-item__remove" type="button" aria-label={`Usuń z koszyka: ${product.name}`} onClick={() => onRemove(product.id)}>Usuń</button>
      </div>
      <div className="cart-item__quantity" role="group" aria-label={`Liczba sztuk: ${product.name}`}>
        <button type="button" aria-label={`Zmniejsz liczbę: ${product.name}`} disabled={product.quantity <= 1} onClick={() => onQuantityChange(product.id, product.quantity - 1)}>−</button>
        <span aria-label={`${product.quantity} sztuk`}>{product.quantity}</span>
        <button type="button" aria-label={`Zwiększ liczbę: ${product.name}`} disabled={product.quantity >= 99} onClick={() => onQuantityChange(product.id, product.quantity + 1)}>+</button>
      </div>
      <strong className="cart-item__total">{formatPrice(product.price * product.quantity)}</strong>
    </li>
  )
}

export default CartItem
