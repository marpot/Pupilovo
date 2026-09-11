import {
  useEffect,
  useState,
  type MouseEvent,
} from 'react'
import { Link, useNavigate } from 'react-router-dom'

import {
  CART_UPDATED_EVENT,
  getCart,
} from '@/api/cart'
import ProductSearch from '@/components/ProductSearch/ProductSearch'

import '@/components/Header/Header.scss'

function Header() {
  const navigate = useNavigate()
  const [cartCount, setCartCount] = useState(0)

  useEffect(() => {
    const controller = new AbortController()

    const loadCartCount = async () => {
      try {
        const cart = await getCart(controller.signal)

        setCartCount(cart.items_count)
      } catch (error) {
        if (
          error instanceof DOMException &&
          error.name === 'AbortError'
        ) {
          return
        }

        console.error(
          'Nie udało się pobrać licznika koszyka:',
          error,
        )
      }
    }

    const handleCartUpdated = (event: Event) => {
      const cartEvent = event as CustomEvent<{
        count: number
      }>

      setCartCount(cartEvent.detail.count)
    }

    window.addEventListener(
      CART_UPDATED_EVENT,
      handleCartUpdated,
    )

    void loadCartCount()

    return () => {
      controller.abort()

      window.removeEventListener(
        CART_UPDATED_EVENT,
        handleCartUpdated,
      )
    }
  }, [])

  const handleHomeClick = (
    event: MouseEvent<HTMLAnchorElement>,
  ) => {
    event.preventDefault()
    navigate('/')

    document.scrollingElement?.scrollTo({
      top: 0,
      behavior: 'smooth',
    })
  }

  const handleShopClick = (
    event: MouseEvent<HTMLAnchorElement>,
  ) => {
    event.preventDefault()
    navigate('/shop')

    requestAnimationFrame(() => {
      document.getElementById('shop')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    })
  }

  const handleAboutClick = (
    event: MouseEvent<HTMLAnchorElement>,
  ) => {
    event.preventDefault()
    navigate('/about')

    requestAnimationFrame(() => {
      document.getElementById('about')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    })
  }

  return (
    <header className="header">
      <div className="header__container">
        <Link to="/" className="header__logo">
          <img
            src="/Pupilovo.svg"
            alt=""
            aria-hidden="true"
          />
        </Link>

        <nav
          className="header__nav"
          aria-label="Główna nawigacja"
        >
          <Link to="/" onClick={handleHomeClick}>
            Strona główna
          </Link>

          <Link to="/about" onClick={handleAboutClick}>
            O nas
          </Link>

          <Link to="/shop" onClick={handleShopClick}>
            Sklep
          </Link>
        </nav>

        <div className="header__actions">
          <ProductSearch />

          <Link to="/account" aria-label="Konto">
            <span aria-hidden="true">👤</span>
            <span>Konto</span>
          </Link>

          <Link
            to="/cart"
            className="header__cart"
            aria-label={`Koszyk, ${cartCount} produktów`}
          >
            <span
              className="header__cart-icon"
              aria-hidden="true"
            >
              🛒

              {cartCount > 0 && (
                <span className="header__cart-count">
                  {cartCount > 99 ? '99+' : cartCount}
                </span>
              )}
            </span>

            <span>Koszyk</span>
          </Link>
        </div>
      </div>
    </header>
  )
}

export default Header