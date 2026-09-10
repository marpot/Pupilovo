import { useEffect, useRef } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'

import '@/pages/Shop/Shop.scss'
import CategoryFilter from '@/components/CategoryFilter/CategoryFilter'
import ProductCard from '@/components/ProductCard/ProductCard'

const products = [
  {
    name: 'Miska spowalniająca Pupilovo',
    price: '59,90 zł',
    image: '/hero.png',
    description:
      'Pomaga spowolnić jedzenie i wspiera zdrowe nawyki Twojego pupila.',
    category: 'psy',
  },
  {
    name: 'Mata węchowa Pupilovo',
    price: '79,90 zł',
    image: '/hero.png',
    description:
      'Zabawa, która angażuje naturalny węch i zapewnia psu dodatkową aktywność.',
    category: 'psy',
  },
  {
    name: 'Zabawka interaktywna Pupilovo',
    price: '49,90 zł',
    image: '/hero.png',
    description:
      'Pomaga zapewnić pupilowi zajęcie i rozwijać jego naturalną ciekawość.',
    category: 'koty',
  },
  {
    name: 'Szczotka pielęgnacyjna Pupilovo',
    price: '39,90 zł',
    image: '/hero.png',
    description:
      'Delikatna pielęgnacja sierści i przyjemny masaż podczas codziennego czesania.',
    category: 'koty',
  },
]

function Shop() {
  const [searchParams, setSearchParams] = useSearchParams()
  const location = useLocation()
  const navigate = useNavigate()
  const shopRef = useRef<HTMLElement>(null)
  const pathnameRef = useRef(location.pathname)

  useEffect(() => {
    pathnameRef.current = location.pathname
  }, [location.pathname])

  const selectedCategory = searchParams.get('category') || 'all'

  useEffect(() => {
    const shopElement = shopRef.current

    if (!shopElement) {
      return
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        const isSectionRoute =
          pathnameRef.current === '/' || pathnameRef.current === '/about'
        const activationOffset = window.innerHeight * 0.3
        const isShopActive =
          entry.boundingClientRect.top <= activationOffset

        if (entry.isIntersecting && isShopActive && isSectionRoute) {
          navigate('/shop', {
            replace: true,
            state: { shopVisible: true },
          })
        }
      },
      {
        root: null,
        rootMargin: '-92px 0px -70% 0px',
        threshold: 0,
      },
    )

    observer.observe(shopElement)

    return () => observer.disconnect()
  }, [navigate])

  const handleCategoryChange = (category: string) => {
    if (category === 'all') {
      setSearchParams({})
      return
    }

    setSearchParams({ category })
  }

  const filteredProducts =
    selectedCategory === 'all'
      ? products
      : products.filter((product) => product.category === selectedCategory)

  return (
    <section ref={shopRef} id="shop" className="shop">
      <section className="shop__products">
        <div className="shop__container">
          <div className="shop__toolbar">
            <h1>Produkty</h1>

            <div className="shop__filters">
              <CategoryFilter
                value={selectedCategory}
                onChange={handleCategoryChange}
              />

              <button type="button">Sortuj</button>
            </div>
          </div>

          <div className="shop__grid">
            {filteredProducts.map((product) => (
              <ProductCard
                key={product.name}
                name={product.name}
                price={product.price}
                image={product.image}
                description={product.description}
              />
            ))}
          </div>
        </div>
      </section>
    </section>
  )
}

export default Shop