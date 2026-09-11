import type { ProductCategory } from '@/types/woocommerce'

import '@/components/CategoryFilter/CategoryFilter.scss'

type CategoryFilterProps = {
  value: string
  categories: ProductCategory[]
  onChange: (value: string) => void
}

function CategoryFilter({
  value,
  categories,
  onChange,
}: CategoryFilterProps) {
  return (
    <label className="category-filter">
      <span className="category-filter__label">Kategoria</span>

      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
      >
        <option value="all">Wszystkie kategorie</option>

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