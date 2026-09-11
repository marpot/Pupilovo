import { Link } from 'react-router-dom'

import { formatPrice } from '@/pages/Cart/cartData'

type CartSummaryProps = {
  subtotal: number
  quantity: number
}

function CartSummary({
  subtotal,
  quantity,
}: CartSummaryProps) {
  return (
    <aside
      className="cart-summary"
      aria-labelledby="cart-summary-title"
    >
      <h2 id="cart-summary-title">
        Podsumowanie
      </h2>

      <dl>
        <div>
          <dt>Produkty ({quantity} szt.)</dt>
          <dd>{formatPrice(subtotal)}</dd>
        </div>

        <div>
          <dt>Dostawa</dt>
          <dd>Do ustalenia</dd>
        </div>

        <div className="cart-summary__total">
          <dt>Suma produktów</dt>
          <dd>{formatPrice(subtotal)}</dd>
        </div>
      </dl>

      <p className="cart-summary__note">
        Koszt dostawy zostanie podany przed złożeniem
        zamówienia.
      </p>

      <Link
        to="/checkout"
        className="cart-summary__checkout"
      >
        Przejdź do zamówienia
      </Link>
    </aside>
  )
}

export default CartSummary