import '@/components/CategorySection/CategorySection.scss'

const categories = [
  {
    name: 'Psy',
    description: 'Karma, przysmaki i akcesoria',
    image: '/categories/dog.jpg',
    href: '/shop?category=psy',
  },
  {
    name: 'Koty',
    description: 'Wszystko dla kocich potrzeb',
    image: '/categories/cat.jpg',
    href: '/shop?category=koty',
  },
  {
    name: 'Gryzonie',
    description: 'Produkty dla małych pupili',
    image: '/categories/rodent.jpg',
    href: '/shop?category=gryzonie',
  },
  {
    name: 'Ptaki',
    description: 'Pokarm i akcesoria dla ptaków',
    image: '/shop?category=ptaki',
  },
]

function CategorySection() {
  return (
    <section id="categories" className="category-section">
      <div className="category-section__container">
        <div className="category-section__header">
          <div>
            <span className="category-section__eyebrow">
              Dla każdego pupila
            </span>

            <h2 className="category-section__title">
              Wybierz coś dla swojego pupila
            </h2>
          </div>
        </div>

        <div className="category-section__grid">
          {categories.map((category) => (
            <a
              key={category.name}
              href={category.href}
              className="category-card"
            >
              <div className="category-card__image">
                <img src={category.image} alt={category.name} />
              </div>

              <div className="category-card__content">
                <h3>{category.name}</h3>
                <p>{category.description}</p>

                <span className="category-card__arrow" aria-hidden="true">
                  →
                </span>
              </div>
            </a>
          ))}
        </div>
      </div>
    </section>
  )
}

export default CategorySection