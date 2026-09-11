import { useEffect, useRef, useState } from "react";
import { useLocation, useNavigate, useSearchParams } from "react-router-dom";

import { getProductCategories, getProducts } from "@/api/woocommerce";
import CategoryFilter from "@/components/CategoryFilter/CategoryFilter";
import ProductCard from "@/components/ProductCard/ProductCard";
import type { Product, ProductCategory } from "@/types/woocommerce";

import "@/pages/Shop/Shop.scss";

const normalizeSearch = (value: string) =>
  value
    .toLocaleLowerCase("pl")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/ł/g, "l");

function Shop() {
  const [searchParams, setSearchParams] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();

  const shopRef = useRef<HTMLElement>(null);
  const pathnameRef = useRef(location.pathname);
  const searchRef = useRef(location.search);

  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<ProductCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    pathnameRef.current = location.pathname;
    searchRef.current = location.search;
  }, [location.pathname, location.search]);

  useEffect(() => {
    const controller = new AbortController();

    const loadCatalog = async () => {
      try {
        setIsLoading(true);
        setError(null);

        const [productsData, categoriesData] = await Promise.all([
          getProducts(controller.signal),
          getProductCategories(controller.signal),
        ]);

        setProducts(productsData);
        setCategories(categoriesData);
      } catch (catalogError) {
        if (
          catalogError instanceof DOMException &&
          catalogError.name === "AbortError"
        ) {
          return;
        }

        console.error(catalogError);

        setError("Nie udało się pobrać produktów. Spróbuj ponownie później.");
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    };

    void loadCatalog();

    return () => controller.abort();
  }, []);

  useEffect(() => {
    const shopElement = shopRef.current;

    if (!shopElement) {
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        const isSectionRoute =
          pathnameRef.current === "/" || pathnameRef.current === "/about";

        const activationOffset = window.innerHeight * 0.3;
        const isShopActive = entry.boundingClientRect.top <= activationOffset;

        if (entry.isIntersecting && isShopActive && isSectionRoute) {
          navigate(
            {
              pathname: "/shop",
              search: searchRef.current,
            },
            {
              replace: true,
              state: { shopVisible: true },
            },
          );
        }
      },
      {
        root: null,
        rootMargin: "-92px 0px -70% 0px",
        threshold: 0,
      },
    );

    observer.observe(shopElement);

    return () => observer.disconnect();
  }, [navigate]);

  const selectedCategory = searchParams.get("category") || "all";
  const query = searchParams.get("q")?.trim() || "";

  const searchWords = normalizeSearch(query)
    .split(/\s+/)
    .filter(Boolean);

  const handleCategoryChange = (category: string) => {
    const params = new URLSearchParams(searchParams);

    if (category === "all") {
      params.delete("category");
    } else {
      params.set("category", category);
    }

    setSearchParams(params);
  };

  const clearSearch = () => {
    const params = new URLSearchParams(searchParams);

    params.delete("q");

    setSearchParams(params);
  };

  const showAllProducts = () => {
    setSearchParams({});
  };

  const filteredProducts = products.filter((product) => {
    const matchesCategory =
      selectedCategory === "all" || product.category === selectedCategory;

    const text = normalizeSearch(`${product.name} ${product.description}`);

    const matchesSearch = searchWords.every((word) => text.includes(word));

    return matchesCategory && matchesSearch;
  });

  return (
    <section ref={shopRef} id="shop" className="shop">
      <section className="shop__products">
        <div className="shop__container">
          <div className="shop__toolbar">
            <h1>Produkty</h1>

            <div className="shop__filters">
              <CategoryFilter
                value={selectedCategory}
                categories={categories}
                onChange={handleCategoryChange}
              />

              <button type="button">Sortuj</button>
            </div>
          </div>

          {isLoading && (
            <div className="shop__loading" role="status">
              Ładowanie produktów...
            </div>
          )}

          {error && (
            <div className="shop__error" role="alert">
              <p>{error}</p>
            </div>
          )}

          {!isLoading && !error && (
            <>
              <div className="shop__search-info">
                <p role="status">
                  {query
                    ? `Wyniki dla „${query}”: ${filteredProducts.length}`
                    : `Liczba produktów: ${filteredProducts.length}`}
                </p>

                {query && (
                  <button type="button" onClick={clearSearch}>
                    Wyczyść wyszukiwanie
                  </button>
                )}
              </div>

              {filteredProducts.length === 0 && (
                <div className="shop__empty">
                  <h2>Nie znaleźliśmy produktów</h2>

                  <p>Spróbuj innej nazwy lub zmień kategorię.</p>

                  <button type="button" onClick={showAllProducts}>
                    Pokaż wszystkie produkty
                  </button>
                </div>
              )}

              <div className="shop__grid">
                {filteredProducts.map((product) => (
                  <ProductCard
                    key={product.id}
                    id={product.id}
                    name={product.name}
                    price={product.price}
                    image={product.image}
                    description={product.description}
                    available={product.available}
                  />
                ))}
              </div>
            </>
          )}
        </div>
      </section>
    </section>
  );
}

export default Shop;