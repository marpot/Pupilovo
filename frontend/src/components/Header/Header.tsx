import {
  useEffect,
  useRef,
  useState,
  type MouseEvent,
} from 'react'
import { Link, useNavigate } from 'react-router-dom'

import {
  CART_UPDATED_EVENT,
  getCart,
} from '@/api/cart'
import ProductSearch from '@/components/ProductSearch/ProductSearch'
import { beginSectionNavigation } from '@/utils/sectionNavigation'

import '@/components/Header/Header.scss'

const getProductCountLabel = (count: number) => {
  const absoluteCount = Math.abs(Math.trunc(count))
  const lastDigit = absoluteCount % 10
  const lastTwoDigits = absoluteCount % 100

  if (absoluteCount === 1) return 'produkt'

  if (
    lastDigit >= 2 &&
    lastDigit <= 4 &&
    (lastTwoDigits < 12 || lastTwoDigits > 14)
  ) {
    return 'produkty'
  }

  return 'produktów'
}

function Header() {
  const navigate = useNavigate()
  const [cartCount, setCartCount] = useState(0)
  const [isMenuOpen, setIsMenuOpen] = useState(false)
  const menuButtonRef = useRef<HTMLButtonElement>(null)
  const navigationRef = useRef<HTMLElement>(null)

  const closeMenu = () => setIsMenuOpen(false)

  useEffect(() => {
    if (!isMenuOpen) return

    const previousOverflow = document.body.style.overflow

    document.body.style.overflow = 'hidden'
    navigationRef.current
      ?.querySelector<HTMLAnchorElement>('a')
      ?.focus()

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return

      setIsMenuOpen(false)
      menuButtonRef.current?.focus()
    }

    const handleResize = () => {
      if (window.innerWidth > 768) {
        setIsMenuOpen(false)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    window.addEventListener('resize', handleResize)

    return () => {
      document.body.style.overflow = previousOverflow
      document.removeEventListener('keydown', handleKeyDown)
      window.removeEventListener('resize', handleResize)
    }
  }, [isMenuOpen])

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
    closeMenu()
    beginSectionNavigation('/')
    navigate('/', {
      state: { sectionNavigation: true },
    })
  }

  const handleShopClick = (
    event: MouseEvent<HTMLAnchorElement>,
  ) => {
    event.preventDefault()
    closeMenu()
    beginSectionNavigation('/shop')
    navigate('/shop', {
      state: { sectionNavigation: true },
    })
  }

  const handleAboutClick = (
    event: MouseEvent<HTMLAnchorElement>,
  ) => {
    event.preventDefault()
    closeMenu()
    beginSectionNavigation('/about')
    navigate('/about', {
      state: { sectionNavigation: true },
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
          ref={navigationRef}
          id="main-navigation"
          className={`header__nav${isMenuOpen ? ' header__nav--open' : ''}`}
          aria-label="Główna nawigacja"
        >
          <Link to="/" onClick={handleHomeClick}>
            Strona główna
          </Link>

          <Link to="/shop" onClick={handleShopClick}>
            Sklep
          </Link>

          <Link to="/about" onClick={handleAboutClick}>
            O nas
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
            aria-label={`Koszyk, ${cartCount} ${getProductCountLabel(cartCount)}`}
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

        <button
          ref={menuButtonRef}
          type="button"
          className="header__menu-toggle"
          aria-label={
            isMenuOpen ? 'Zamknij menu' : 'Otwórz menu'
          }
          aria-controls="main-navigation"
          aria-expanded={isMenuOpen}
          onClick={() => setIsMenuOpen((open) => !open)}
        >
          <span aria-hidden="true" />
          <span aria-hidden="true" />
          <span aria-hidden="true" />
        </button>
      </div>

      {isMenuOpen && (
        <button
          type="button"
          className="header__menu-backdrop"
          aria-label="Zamknij menu"
          onClick={() => {
            closeMenu()
            menuButtonRef.current?.focus()
          }}
        />
      )}
    </header>
  )
}

export default Header
