import { useState } from 'react'

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
  const searchParams = new URLSearchParams(window.location.search)
  const categoryFromUrl = searchParams.get('category') || 'all'

  const [selectedCategory, setSelectedCategory] = useState(categoryFromUrl)

  const handleCategoryChange = (category: string) => {
    setSelectedCategory(category)

    const params = new URLSearchParams(window.location.search)

    if (category === 'all') {
      params.delete('category')
    } else {
      params.set('category', category)
    }

    const queryString = params.toString()
    const newUrl = queryString ? `/shop?${queryString}` : '/shop'

    window.history.pushState({}, '', newUrl)
    window.dispatchEvent(new Event('locationchange'))
  }

  const filteredProducts =
    selectedCategory === 'all'
      ? products
      : products.filter((product) => product.category === selectedCategory)

  return (
    <section id="shop" className="shop">
      <section className="shop__products">
        <div className="shop__container">
          <div className="shop__toolbar">
            <h1>Produkty</h1>

            <div className="shop__filters">
              <CategoryFilter
                value={selectedCategory}
                onChange={handleCategoryChange}
              />

              <button type="button">
                Sortuj
              </button>
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