import '@/components/CategoryFilter/CategoryFilter.scss'

const categories = [
  {
    name: 'Wszystkie kategorie',
    value: 'all',
  },
  {
    name: 'Psy',
    value: 'psy',
  },
  {
    name: 'Koty',
    value: 'koty',
  },
  {
    name: 'Gryzonie',
    value: 'gryzonie',
  },
  {
    name: 'Ptaki',
    value: 'ptaki',
  },
]

function CategoryFilter() {
  return (
    <label className="category-filter">
      <span className="category-filter__label">Kategoria</span>

      <select defaultValue="all">
        {categories.map((category) => (
          <option key={category.value} value={category.value}>
            {category.name}
          </option>
        ))}
      </select>
    </label>
  )
}

export default CategoryFilter