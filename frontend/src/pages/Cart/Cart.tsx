import {
  useEffect,
  useState,
} from 'react'
import { Link } from 'react-router-dom'

import {
  getCart,
  removeCartItem,
  updateCartItem,
} from '@/api/cart'
import CartItem from '@/components/CartItem/CartItem'
import CartSummary from '@/components/CartSummary/CartSummary'
import {
  formatPrice,
  mapCartProduct,
  type CartProduct,
} from '@/pages/Cart/cartData'

import '@/pages/Cart/Cart.scss'

function Cart() {
  const [products, setProducts] = useState<CartProduct[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [announcement, setAnnouncement] = useState('')

  useEffect(() => {
    window.scrollTo({
      top: 0,
      behavior: 'instant',
    })

    const controller = new AbortController()

    const loadCart = async () => {
      try {
        setIsLoading(true)
        setError(null)

        const cart = await getCart(controller.signal)

        setProducts(cart.items.map(mapCartProduct))
      } catch (cartError) {
        if (
          cartError instanceof DOMException &&
          cartError.name === 'AbortError'
        ) {
          return
        }

        console.error(cartError)

        setError('Nie udało się pobrać koszyka.')
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false)
        }
      }
    }

    void loadCart()

    return () => controller.abort()
  }, [])

  const quantity = products.reduce(
    (sum, product) => sum + product.quantity,
    0,
  )

  const subtotal = products.reduce(
    (sum, product) =>
      sum + product.quantity * product.price,
    0,
  )

  const handleQuantityChange = async (
    id: string,
    nextQuantity: number,
  ) => {
    if (
      !Number.isInteger(nextQuantity) ||
      nextQuantity < 1 ||
      nextQuantity > 99
    ) {
      return
    }

    const previousProducts = products

    setProducts((current) =>
      current.map((product) =>
        product.id === id
          ? {
              ...product,
              quantity: nextQuantity,
            }
          : product,
      ),
    )

    try {
      const cart = await updateCartItem(
        id,
        nextQuantity,
      )

      setProducts(
        cart.items.map(mapCartProduct),
      )

      setAnnouncement(
        'Zmieniono liczbę produktów.',
      )
    } catch (cartError) {
      console.error(cartError)

      setProducts(previousProducts)

      setAnnouncement(
        'Nie udało się zmienić liczby produktów.',
      )
    }
  }

  const handleRemove = async (
    id: string,
  ) => {
    const product = products.find(
      (item) => item.id === id,
    )

    try {
      const cart = await removeCartItem(id)

      setProducts(
        cart.items.map(mapCartProduct),
      )

      setAnnouncement(
        `Usunięto: ${product?.name ?? 'produkt'}.`,
      )
    } catch (cartError) {
      console.error(cartError)

      setAnnouncement(
        'Nie udało się usunąć produktu.',
      )
    }
  }

  return (
    <main className="cart">
      <div className="cart__container">
        <header className="cart__heading">
          <p className="cart__eyebrow">
            Dobry wybór dla Twojego pupila
          </p>

          <h1>Twój koszyk</h1>

          <p>
            Wszystko, czego potrzebuje. O krok bliżej domu.
          </p>
        </header>

        {isLoading && (
          <p
            className="cart__status"
            role="status"
          >
            Ładowanie koszyka...
          </p>
        )}

        {error && (
          <p
            className="cart__status"
            role="alert"
          >
            {error}
          </p>
        )}

        {!isLoading && !error && (
          <>
            <p
              className="cart__status"
              role="status"
            >
              {announcement &&
                `${announcement} Suma produktów: ${formatPrice(subtotal)}.`}
            </p>

            {products.length ? (
              <div className="cart__layout">
                <section
                  className="cart__products"
                  aria-label="Produkty w koszyku"
                >
                  <div className="cart__list-heading">
                    <h2>Twoje produkty</h2>
                    <span>{quantity} szt.</span>
                  </div>

                  <ul className="cart__list">
                    {products.map((product) => (
                      <CartItem
                        key={product.id}
                        product={product}
                        onQuantityChange={
                          handleQuantityChange
                        }
                        onRemove={
                          handleRemove
                        }
                      />
                    ))}
                  </ul>

                  <Link
                    className="cart__back"
                    to="/shop#shop"
                  >
                    ← Kontynuuj zakupy
                  </Link>
                </section>

                <CartSummary
                  subtotal={subtotal}
                  quantity={quantity}
                />
              </div>
            ) : (
              <section
                className="cart__empty"
                aria-labelledby="empty-cart-title"
              >
                <span
                  className="cart__icon"
                  aria-hidden="true"
                >
                  ♡
                </span>

                <h2 id="empty-cart-title">
                  Twój koszyk czeka na coś dobrego
                </h2>

                <p>
                  Nie ma tu jeszcze produktów. Znajdź coś dla swojego pupila.
                </p>

                <Link
                  className="cart__shop-link"
                  to="/shop#shop"
                >
                  Przejdź do sklepu
                </Link>
              </section>
            )}
          </>
        )}
      </div>
    </main>
  )
}

export default Cart