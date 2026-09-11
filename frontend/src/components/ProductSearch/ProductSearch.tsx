import { useRef } from 'react'
import type { FormEvent } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate, useSearchParams } from 'react-router-dom'

import '@/components/ProductSearch/ProductSearch.scss'

function ProductSearch() {
  const dialogRef = useRef<HTMLDialogElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const openSearch = () => {
    if (inputRef.current) inputRef.current.value = searchParams.get('q') || ''
    dialogRef.current?.showModal()
    inputRef.current?.focus()
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const query = inputRef.current?.value.trim() || ''
    const params = new URLSearchParams(searchParams)
    params.delete('preview')
    if (query) params.set('q', query)
    else params.delete('q')

    dialogRef.current?.close()
    navigate({ pathname: '/shop', search: params.toString(), hash: '#shop' })
  }

  return (
    <>
      <button type="button" aria-label="Szukaj" aria-haspopup="dialog" onClick={openSearch}>
        <span aria-hidden="true">🔍</span>
        <span>Szukaj</span>
      </button>
      {createPortal(
        <dialog className="product-search" ref={dialogRef} aria-labelledby="product-search-title">
          <div className="product-search__heading">
            <h2 id="product-search-title">Czego szuka Twój pupil?</h2>
            <button className="product-search__close" type="button" aria-label="Zamknij wyszukiwanie" onClick={() => dialogRef.current?.close()}>×</button>
          </div>
          <p>Znajdź produkt po nazwie lub opisie.</p>
          <form role="search" onSubmit={handleSubmit}>
            <label htmlFor="product-search-query">Szukaj produktów</label>
            <div className="product-search__controls">
              <input ref={inputRef} id="product-search-query" name="q" type="search" placeholder="Np. miska, mata, zabawka" maxLength={200} />
              <button className="product-search__submit" type="submit">Szukaj</button>
            </div>
          </form>
        </dialog>,
        document.body,
      )}
    </>
  )
}

export default ProductSearch
