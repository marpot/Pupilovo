import '@/components/Header/Header.scss'

function Header() {
  const isShopPage = window.location.pathname === '/shop'
  const homeHref = isShopPage ? '/#home' : '#home'
  const shopHref = isShopPage ? '/#shop' : '#shop'

  return (
    <header className="header">
      <div className="header__container">
        <a href={homeHref} className="header__logo">
          <img src="/Pupilovo.svg" alt="" aria-hidden="true" />
        </a>

        <nav className="header__nav" aria-label="Główna nawigacja">
          <a href={homeHref}>Strona główna</a>
          <a href={shopHref}>Sklep</a>
          <a href={isShopPage ? '/#about' : '#about'}>O nas</a>
        </nav>

        <div className="header__actions">
          <button type="button" aria-label="Szukaj">
            <span aria-hidden="true">🔍</span>
            <span>Szukaj</span>
          </button>

          <a href="/account" aria-label="Konto">
            <span aria-hidden="true">👤</span>
            <span>Konto</span>
          </a>

          <a href="/cart" aria-label="Koszyk">
            <span aria-hidden="true">🛒</span>
            <span>Koszyk</span>
          </a>
        </div>
      </div>
    </header>
  )
}

export default Header