import { Link, useLocation, useParams } from 'react-router-dom'

import '@/pages/OrderConfirmation/OrderConfirmation.scss'

type OrderState = {
  orderId?: number
  orderNumber?: string
  status?: string
}

function OrderConfirmation() {
  const { id } = useParams()
  const location = useLocation()

  const state = location.state as OrderState | null

  const orderNumber =
    state?.orderNumber ??
    state?.orderId?.toString() ??
    id ??
    ''

  return (
    <main className="order-confirmation">
      <div className="order-confirmation__container">
        <section className="order-confirmation__card">
          <div
            className="order-confirmation__icon"
            aria-hidden="true"
          >
            ✓
          </div>

          <p className="order-confirmation__eyebrow">
            Zamówienie przyjęte
          </p>

          <h1>Dziękujemy za zamówienie!</h1>

          <p className="order-confirmation__message">
            Twoje zamówienie zostało zapisane
            w WooCommerce.
          </p>

          {orderNumber && (
            <p className="order-confirmation__number">
              Numer zamówienia:
              <strong> #{orderNumber}</strong>
            </p>
          )}

          <div className="order-confirmation__actions">
            <Link
              to="/shop#shop"
              className="order-confirmation__shop"
            >
              Wróć do sklepu
            </Link>

            <Link
              to="/"
              className="order-confirmation__home"
            >
              Strona główna
            </Link>
          </div>
        </section>
      </div>
    </main>
  )
}

export default OrderConfirmation