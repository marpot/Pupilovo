import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { api } from './api';
import type {
  FulfillmentGroup,
  FulfillmentHistoryEntry,
  FulfillmentItem,
  FulfillmentOrderDetails,
  FulfillmentStatus,
  PaginatedFulfillmentGroups,
  PaginatedSuppliers,
  Supplier,
} from './types';

const statuses: Array<{ value: FulfillmentStatus; label: string }> = [
  { value: 'pending', label: 'Oczekuje' },
  { value: 'ready', label: 'Gotowe' },
  { value: 'manually_approved', label: 'Zatwierdzone ręcznie' },
  { value: 'sent', label: 'Wysłane' },
  { value: 'acknowledged', label: 'Potwierdzone przez hurtownię' },
  { value: 'shipped', label: 'W drodze' },
  { value: 'delivered', label: 'Dostarczone' },
  { value: 'failed', label: 'Błąd' },
  { value: 'cancelled', label: 'Anulowane' },
];

type Filters = { supplierId: string; status: string; manual: string };

const initialFilters: Filters = { supplierId: '', status: '', manual: '' };

export function FulfillmentView() {
  const [data, setData] = useState<PaginatedFulfillmentGroups | null>(null);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [filters, setFilters] = useState<Filters>(initialFilters);
  const [applied, setApplied] = useState<Filters>(initialFilters);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reloadKey, setReloadKey] = useState(0);
  const [selectedGroupId, setSelectedGroupId] = useState<number | null>(null);
  const [details, setDetails] = useState<FulfillmentGroup | null>(null);
  const [detailsLoading, setDetailsLoading] = useState(false);
  const [detailsError, setDetailsError] = useState('');
  const detailRequest = useRef(0);

  useEffect(() => {
    let active = true;
    api<PaginatedSuppliers>('/suppliers?per_page=100')
      .then((response) => { if (active) setSuppliers(response.items); })
      .catch(() => { if (active) setSuppliers([]); });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    let active = true;
    const params = new URLSearchParams({ page: String(page), per_page: '20' });
    if (applied.supplierId) params.set('supplier_id', applied.supplierId);
    if (applied.status) params.set('status', applied.status);
    if (applied.manual) params.set('manual', applied.manual);

    api<PaginatedFulfillmentGroups>(`/fulfillment/orders?${params}`)
      .then((response) => { if (active) setData(response); })
      .catch((reason: Error) => { if (active) setError(reason.message); })
      .finally(() => { if (active) setLoading(false); });

    return () => { active = false; };
  }, [applied, page, reloadKey]);

  const applyFilters = (event: FormEvent) => {
    event.preventDefault();
    detailRequest.current += 1;
    setLoading(true);
    setError('');
    setPage(1);
    setSelectedGroupId(null);
    setDetails(null);
    setApplied({ ...filters });
  };

  const clearFilters = () => {
    detailRequest.current += 1;
    setLoading(true);
    setError('');
    setFilters(initialFilters);
    setApplied({ ...initialFilters });
    setPage(1);
    setSelectedGroupId(null);
    setDetails(null);
  };

  const retryList = () => {
    setLoading(true);
    setError('');
    setReloadKey((value) => value + 1);
  };

  const changePage = (nextPage: number) => {
    detailRequest.current += 1;
    setLoading(true);
    setError('');
    setPage(nextPage);
    setDetails(null);
    setSelectedGroupId(null);
  };

  const loadDetails = useCallback(async (group: FulfillmentGroup) => {
    if (selectedGroupId === group.id) {
      detailRequest.current += 1;
      setSelectedGroupId(null);
      setDetails(null);
      setDetailsError('');
      return;
    }

    const requestId = ++detailRequest.current;
    setSelectedGroupId(group.id);
    setDetails(null);
    setDetailsError('');
    setDetailsLoading(true);
    try {
      const response = await api<FulfillmentOrderDetails>(`/fulfillment/orders/${group.orderId}`);
      if (requestId !== detailRequest.current) return;
      const selected = response.groups.find((item) => item.id === group.id);
      if (!selected) throw new Error('Nie znaleziono wybranej grupy realizacji.');
      setDetails(selected);
    } catch (reason) {
      if (requestId === detailRequest.current) setDetailsError((reason as Error).message);
    } finally {
      if (requestId === detailRequest.current) setDetailsLoading(false);
    }
  }, [selectedGroupId]);

  return (
    <section aria-labelledby="fulfillment-heading">
      <div className="psh-section-heading">
        <div>
          <h2 id="fulfillment-heading">Zamówienia dropshippingowe</h2>
          <p>Wewnętrzny podział zamówień WooCommerce według hurtowni. Ten widok nie wysyła zamówień i nie zmienia ich statusów.</p>
        </div>
        {data && <span className="psh-result-count">{data.total.toLocaleString('pl-PL')} grup</span>}
      </div>

      <form className="psh-filterbar psh-fulfillment-filters" onSubmit={applyFilters}>
        <label>
          <span>Hurtownia</span>
          <select value={filters.supplierId} onChange={(event) => setFilters({ ...filters, supplierId: event.target.value })}>
            <option value="">Wszystkie hurtownie</option>
            {suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}
          </select>
        </label>
        <label>
          <span>Status realizacji</span>
          <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
            <option value="">Wszystkie statusy</option>
            {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
          </select>
        </label>
        <label>
          <span>Przypisanie</span>
          <select value={filters.manual} onChange={(event) => setFilters({ ...filters, manual: event.target.value })}>
            <option value="">Wszystkie pozycje</option>
            <option value="1">Wymagające decyzji</option>
            <option value="0">Przypisane automatycznie</option>
          </select>
        </label>
        <button className="psh-button psh-button--primary" type="submit">Filtruj</button>
        <button className="psh-button" type="button" onClick={clearFilters}>Wyczyść</button>
      </form>

      {error && (
        <div className="psh-error" role="alert">
          <strong>Nie udało się pobrać realizacji.</strong>
          <span>{error}</span>
          <button className="psh-button" type="button" onClick={retryList}>Spróbuj ponownie</button>
        </div>
      )}
      {loading && <FulfillmentSkeleton />}
      {!loading && !error && data?.items.length === 0 && (
        <div className="psh-panel psh-empty">
          <strong>Brak grup realizacji</strong>
          <p>Dla wybranych filtrów nie znaleziono zamówień dropshippingowych.</p>
        </div>
      )}
      {!loading && !error && data && data.items.length > 0 && (
        <>
          <div className="psh-table-wrap">
            <table className="psh-table psh-fulfillment-table">
              <thead><tr><th>Zamówienie</th><th>Hurtownia</th><th>Produkty</th><th>Data</th><th>Status</th><th>Błędy</th><th><span className="screen-reader-text">Szczegóły</span></th></tr></thead>
              <tbody>{data.items.map((group) => (
                <tr key={group.id} className={group.requiresManualDecision ? 'psh-fulfillment-row--manual' : undefined}>
                  <td><strong>#{group.orderId}</strong><small className="psh-cell-note">Grupa {group.id}</small></td>
                  <td>{group.supplierName || <span className="psh-manual-label">Brak przypisanej hurtowni</span>}</td>
                  <td>{group.itemCount.toLocaleString('pl-PL')}<small className="psh-cell-note">Ilość: {formatQuantity(group.totalQuantity)}</small></td>
                  <td>{formatDate(group.createdAt)}</td>
                  <td><StatusBadge status={group.status} />{group.requiresManualDecision && <small className="psh-cell-note psh-cell-note--warning">Wymaga decyzji</small>}</td>
                  <td>{group.errorCount > 0 ? <span className="psh-error-count">{group.errorCount} · {errorLabel(group.lastErrorCode)}</span> : '—'}</td>
                  <td className="psh-actions"><button type="button" className="psh-link-button" aria-expanded={selectedGroupId === group.id} aria-controls={`fulfillment-details-${group.id}`} onClick={() => void loadDetails(group)}>{selectedGroupId === group.id ? 'Ukryj' : 'Szczegóły'}</button></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
          <div className="psh-pagination" aria-label="Paginacja realizacji">
            <button type="button" className="psh-button" disabled={page <= 1 || loading} onClick={() => changePage(page - 1)}>← Poprzednia</button>
            <span>Strona {data.page} z {data.totalPages}</span>
            <button type="button" className="psh-button" disabled={page >= data.totalPages || loading} onClick={() => changePage(page + 1)}>Następna →</button>
          </div>
        </>
      )}

      {selectedGroupId !== null && (
        <div id={`fulfillment-details-${selectedGroupId}`} className="psh-panel psh-fulfillment-details" aria-live="polite">
          {detailsLoading && <FulfillmentSkeleton />}
          {detailsError && <div className="psh-error" role="alert"><strong>Nie udało się pobrać szczegółów.</strong><span>{detailsError}</span></div>}
          {details && <FulfillmentDetails group={details} />}
        </div>
      )}
    </section>
  );
}

function FulfillmentDetails({ group }: { group: FulfillmentGroup }) {
  return (
    <>
      <div className="psh-fulfillment-details__heading">
        <div><p className="psh-eyebrow">Zamówienie #{group.orderId}</p><h3>{group.supplierName || 'Pozycje bez przypisanej hurtowni'}</h3></div>
        <StatusBadge status={group.status} />
      </div>
      {group.requiresManualDecision && <div className="psh-manual-notice" role="status"><strong>Wymagana ręczna decyzja</strong><span>Co najmniej jedna pozycja nie ma jednoznacznego, aktywnego powiązania z hurtownią.</span></div>}
      <h4>Pozycje realizacji</h4>
      <div className="psh-fulfillment-products">
        {(group.items || []).map((item) => <FulfillmentProduct key={item.id} item={item} fallbackCurrency={group.currency} />)}
        {(group.items || []).length === 0 && <p className="psh-help">Brak zapisanych pozycji w tej grupie.</p>}
      </div>
      <h4>Historia statusów i błędów</h4>
      <ol className="psh-fulfillment-history">
        {(group.history || []).map((entry) => <HistoryEntry key={entry.id} entry={entry} />)}
        {(group.history || []).length === 0 && <li className="psh-help">Brak zdarzeń historii.</li>}
      </ol>
    </>
  );
}

function FulfillmentProduct({ item, fallbackCurrency }: { item: FulfillmentItem; fallbackCurrency: string | null }) {
  const snapshot = item.snapshot;
  const currency = snapshot.currency || fallbackCurrency || '';
  return (
    <article className={`psh-fulfillment-product${item.supplierId === null ? ' psh-fulfillment-product--manual' : ''}`}>
      <div className="psh-fulfillment-product__name">
        <strong>{snapshot.productName || `Produkt #${item.productId}`}</strong>
        <span>SKU: {snapshot.sku || 'brak'}</span>
        {item.supplierId === null && <span className="psh-manual-label">Brak przypisanej hurtowni</span>}
      </div>
      <dl>
        <div><dt>Ilość</dt><dd>{formatQuantity(item.quantity)}</dd></div>
        <div><dt>Wartość</dt><dd>{formatMoney(snapshot.total, currency)}</dd></div>
        <div><dt>Podatek</dt><dd>{formatMoney(snapshot.totalTax, currency)}</dd></div>
        <div><dt>ID produktu WC</dt><dd>{item.variationId || item.productId || '—'}</dd></div>
        <div><dt>ID powiązania</dt><dd>{item.productLinkId || '—'}</dd></div>
        <div><dt>ID u dostawcy</dt><dd>{snapshot.supplierExternalId || '—'}</dd></div>
      </dl>
      <small>Przypisanie: {assignmentLabel(item.assignmentReason)}</small>
    </article>
  );
}

function HistoryEntry({ entry }: { entry: FulfillmentHistoryEntry }) {
  const hasError = Boolean(entry.errorCode) || entry.eventType === 'assignment_required';
  return (
    <li className={hasError ? 'is-error' : undefined}>
      <span className="psh-fulfillment-history__marker" aria-hidden="true" />
      <div>
        <div className="psh-fulfillment-history__heading"><strong>{historyLabel(entry)}</strong><time dateTime={toIsoDate(entry.createdAt)}>{formatDate(entry.createdAt)}</time></div>
        <p>{entry.message}</p>
        {entry.errorCode && <small>Kod: {errorLabel(entry.errorCode)}</small>}
      </div>
    </li>
  );
}

function StatusBadge({ status }: { status: FulfillmentStatus }) {
  const label = statuses.find((item) => item.value === status)?.label || status;
  return <span className={`psh-badge psh-fulfillment-status psh-fulfillment-status--${status}`}>{label}</span>;
}

function FulfillmentSkeleton() {
  return <div className="psh-table-skeleton" role="status" aria-label="Ładowanie realizacji"><span /><span /><span /></div>;
}

function formatQuantity(value: number) {
  return value.toLocaleString('pl-PL', { maximumFractionDigits: 4 });
}

function formatMoney(value: string, currency: string) {
  const amount = Number(value);
  if (!Number.isFinite(amount)) return '—';
  if (!currency) return amount.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  try { return new Intl.NumberFormat('pl-PL', { style: 'currency', currency }).format(amount); }
  catch { return `${amount.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`; }
}

function normalizeDate(value: string) {
  return value.includes('T') ? value : `${value.replace(' ', 'T')}Z`;
}

function toIsoDate(value: string) {
  const date = new Date(normalizeDate(value));
  return Number.isNaN(date.getTime()) ? '' : date.toISOString();
}

function formatDate(value: string) {
  const date = new Date(normalizeDate(value));
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function historyLabel(entry: FulfillmentHistoryEntry) {
  if (entry.eventType === 'assignment_required') return 'Wymagane przypisanie hurtowni';
  if (entry.toStatus) return `Status: ${statuses.find((item) => item.value === entry.toStatus)?.label || entry.toStatus}`;
  return entry.eventType.replaceAll('_', ' ');
}

function errorLabel(code: string | null) {
  if (!code) return 'Brak szczegółów';
  return ({
    missing_supplier_link: 'brak powiązania z hurtownią',
    ambiguous_supplier_links: 'niejednoznaczne powiązania',
    multiple_primary_links: 'wiele głównych powiązań',
    supplier_metadata_without_active_link: 'nieaktywne powiązanie z metadanych',
    ambiguous_supplier_metadata_links: 'niejednoznaczne metadane dostawcy',
  } as Record<string, string>)[code] || code.replaceAll('_', ' ');
}

function assignmentLabel(reason: string) {
  return ({
    product_supplier_metadata: 'potwierdzone metadanymi produktu',
    primary_product_link: 'główne powiązanie produktu',
    single_product_link: 'jedyne aktywne powiązanie',
  } as Record<string, string>)[reason] || errorLabel(reason);
}
