import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'

import { getFeaturedProducts, getProductCategories } from '@/api/woocommerce'
import ProductCard, { ProductCardSkeleton } from '@/components/ProductCard/ProductCard'
import type { Product, ProductCategory } from '@/types/woocommerce'
import { beginSectionNavigation } from '@/utils/sectionNavigation'

import '@/components/HomeDiscovery/HomeDiscovery.scss'

const categoryFallbackImages: Record<string, string> = {
  psy: '/assets/categories/dog.jpg',
  koty: '/assets/categories/cat.jpg',
}

function HomeDiscovery() {
  const [categories, setCategories] = useState<ProductCategory[]>([])
  const [featured, setFeatured] = useState<Product[]>([])
  const [categoriesLoading, setCategoriesLoading] = useState(true)
  const [featuredLoading, setFeaturedLoading] = useState(true)
  const [categoriesError, setCategoriesError] = useState(false)
  const [featuredError, setFeaturedError] = useState(false)

  useEffect(() => {
    const controller = new AbortController()

    getProductCategories(controller.signal)
      .then(setCategories)
      .catch((error: unknown) => {
        if (!isAbortError(error)) setCategoriesError(true)
      })
      .finally(() => {
        if (!controller.signal.aborted) setCategoriesLoading(false)
      })

    getFeaturedProducts(controller.signal)
      .then(setFeatured)
      .catch((error: unknown) => {
        if (!isAbortError(error)) setFeaturedError(true)
      })
      .finally(() => {
        if (!controller.signal.aborted) setFeaturedLoading(false)
      })

    return () => controller.abort()
  }, [])

  const topLevelCategories = categories.filter((category) => category.parent === 0)
  const categoryTiles = (topLevelCategories.length > 0 ? topLevelCategories : categories).slice(0, 8)

  return (
    <div className="home-discovery">
      <section id="home-categories" className="home-categories" aria-labelledby="home-categories-title">
        <div className="home-section-heading">
          <div>
            <p className="home-section-heading__eyebrow">Znajdź szybciej</p>
            <h2 id="home-categories-title">Wybierz kategorię dla swojego pupila</h2>
            <p>Przejdź bezpośrednio do produktów z wybranej kategorii WooCommerce.</p>
          </div>
        </div>

        {categoriesLoading && <CategorySkeleton />}
        {!categoriesLoading && categoriesError && <InlineState message="Nie udało się teraz pobrać kategorii." />}
        {!categoriesLoading && !categoriesError && categoryTiles.length === 0 && <InlineState message="Kategorie pojawią się tutaj po dodaniu ich w WooCommerce." />}
        {!categoriesLoading && !categoriesError && categoryTiles.length > 0 && (
          <div className="home-categories__grid">
            {categoryTiles.map((category, index) => {
              const image = category.image || categoryFallbackImage(category)
              const visualKind = categoryVisualKind(category)

              return (
                <Link
                  key={category.id}
                  to={`/shop?category=${encodeURIComponent(category.value)}`}
                  state={{ sectionNavigation: true }}
                  className={`home-category home-category--tone-${index % 4}${visualKind ? ` home-category--${visualKind}` : ''}`}
                  onClick={() => beginSectionNavigation('/shop')}
                >
                  <span className="home-category__visual" aria-hidden="true">
                    {image
                      ? <img src={image} alt="" loading="lazy" />
                      : <span>{category.name.trim().charAt(0).toLocaleUpperCase('pl')}</span>}
                  </span>
                  <span className="home-category__content">
                    <span>
                      <strong>{category.name}</strong>
                      <small>{productCountLabel(category.count)}</small>
                    </span>
                    <span className="home-category__cta">Zobacz produkty <span aria-hidden="true">→</span></span>
                  </span>
                </Link>
              )
            })}
          </div>
        )}
      </section>

      <section className="home-featured" aria-labelledby="home-featured-title">
        <div className="home-section-heading home-section-heading--with-action">
          <div>
            <p className="home-section-heading__eyebrow">Warto zobaczyć</p>
            <h2 id="home-featured-title">Polecane produkty</h2>
            <p>Produkty oznaczone jako polecane w aktualnym katalogu sklepu.</p>
          </div>
          <Link to="/shop" state={{ sectionNavigation: true }} onClick={() => beginSectionNavigation('/shop')}>Zobacz cały katalog <span aria-hidden="true">→</span></Link>
        </div>

        {featuredLoading && <div className="home-featured__grid" role="status" aria-label="Ładowanie polecanych produktów">{Array.from({ length: 4 }, (_, index) => <ProductCardSkeleton key={index} />)}</div>}
        {!featuredLoading && featuredError && <InlineState message="Nie udało się teraz pobrać polecanych produktów." />}
        {!featuredLoading && !featuredError && featured.length === 0 && <InlineState title="Polecane produkty są w przygotowaniu" message="Zajrzyj do pełnego katalogu — znajdziesz tam wszystkie aktualnie dostępne produkty." action />}
        {!featuredLoading && !featuredError && featured.length > 0 && (
          <div className="home-featured__grid">
            {featured.map((product) => <ProductCard key={product.id} {...product} />)}
          </div>
        )}
      </section>

      <TrustSection />
    </div>
  )
}

function TrustSection() {
  return (
    <section className="home-trust" aria-labelledby="home-trust-title">
      <div className="home-section-heading">
        <div>
          <p className="home-section-heading__eyebrow">Zakupy bez niedomówień</p>
          <h2 id="home-trust-title">Najważniejsze informacje przed zamówieniem</h2>
        </div>
      </div>
      <div className="home-trust__grid">
        <article><span aria-hidden="true">01</span><h3>Dostawa i płatność</h3><p>Dostępne metody i koszt dostawy zobaczysz podczas składania zamówienia, po podaniu wymaganych danych.</p><Link to="/delivery">Sprawdź informacje o dostawie</Link></article>
        <article><span aria-hidden="true">02</span><h3>Zwroty i reklamacje</h3><p>Przed zakupem zapoznaj się z aktualnymi informacjami dotyczącymi zwrotów oraz sposobu zgłaszania reklamacji.</p><Link to="/returns">Przejdź do zasad zwrotów</Link></article>
        <article><span aria-hidden="true">03</span><h3>Bezpieczne zakupy</h3><p>Koszyk i zamówienia obsługuje WooCommerce. Zakres przetwarzanych danych opisujemy w polityce prywatności.</p><Link to="/privacy">Przeczytaj politykę prywatności</Link></article>
      </div>
    </section>
  )
}

function InlineState({ title, message, action = false }: { title?: string; message: string; action?: boolean }) {
  return (
    <div className="home-inline-state" role="status">
      {title && <h3>{title}</h3>}
      <p>{message}</p>
      {action && <Link to="/shop" state={{ sectionNavigation: true }} onClick={() => beginSectionNavigation('/shop')}>Przejdź do katalogu</Link>}
    </div>
  )
}

function CategorySkeleton() {
  return <div className="home-categories__grid" role="status" aria-label="Ładowanie kategorii">{Array.from({ length: 4 }, (_, index) => <div className="home-category home-category--skeleton" aria-hidden="true" key={index}><span /><strong /><small /></div>)}</div>
}

function productCountLabel(count: number) {
  const absolute = Math.abs(count)
  const lastTwo = absolute % 100
  const last = absolute % 10
  const noun = last === 1 && lastTwo !== 11 ? 'produkt' : last >= 2 && last <= 4 && !(lastTwo >= 12 && lastTwo <= 14) ? 'produkty' : 'produktów'
  return `${count.toLocaleString('pl-PL')} ${noun}`
}

function categoryFallbackImage(category: ProductCategory) {
  const slug = category.value.trim().toLocaleLowerCase('pl-PL')
  const name = category.name.trim().toLocaleLowerCase('pl-PL')

  return categoryFallbackImages[slug] || categoryFallbackImages[name] || null
}

function categoryVisualKind(category: ProductCategory) {
  const slug = category.value.trim().toLocaleLowerCase('pl-PL')
  const name = category.name.trim().toLocaleLowerCase('pl-PL')

  if (slug === 'psy' || name === 'psy') return 'dog'
  if (slug === 'koty' || name === 'koty') return 'cat'
  return null
}

function isAbortError(error: unknown) {
  return error instanceof DOMException && error.name === 'AbortError'
}

export default HomeDiscovery
