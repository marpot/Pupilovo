import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { api } from './api';
import type {
  CatalogProduct,
  DashboardMetrics,
  FeedInspection,
  PaginatedCatalog,
  PaginatedSuppliers,
  Supplier,
  SupplierInput,
  SupplierSource,
  ViewKey,
} from './types';
import { CategoriesView, HistoryView, ImportView, PricingView, SelectedView, SettingsView, SyncView } from './OperationalViews';
import { FulfillmentView } from './FulfillmentView';

const navigation: Array<{ key: ViewKey; label: string; short: string }> = [
  { key: 'dashboard', label: 'Dashboard', short: 'DB' },
  { key: 'suppliers', label: 'Hurtownie', short: 'HU' },
  { key: 'catalog', label: 'Katalog produktów', short: 'KP' },
  { key: 'fulfillment', label: 'Zamówienia dropshippingowe', short: 'ZD' },
  { key: 'categories', label: 'Kategorie', short: 'KA' },
  { key: 'selected', label: 'Wybrane produkty', short: 'WP' },
  { key: 'import', label: 'Import', short: 'IM' },
  { key: 'sync', label: 'Synchronizacja', short: 'SY' },
  { key: 'pricing', label: 'Ceny i marże', short: 'CM' },
  { key: 'history', label: 'Historia i błędy', short: 'HB' },
  { key: 'settings', label: 'Ustawienia', short: 'US' },
];

const sourceLabels: Record<string, string> = {
  file_xml: 'XML z pliku',
  url_xml: 'XML z adresu URL',
  file_csv: 'CSV z pliku',
  url_csv: 'CSV z adresu URL',
  file_tsv: 'TSV z pliku',
  url_tsv: 'TSV z adresu URL',
  file_json: 'JSON z pliku',
  url_json: 'JSON z adresu URL',
  api_adapter: 'Adapter REST API',
};

const mappingFields = [
  ['external_id', 'ID produktu dostawcy'], ['sku', 'SKU'], ['ean', 'EAN / GTIN'],
  ['name', 'Nazwa'], ['description', 'Opis'], ['short_description', 'Opis krótki'],
  ['purchase_price', 'Cena zakupu'], ['tax_rate', 'Stawka VAT'], ['stock', 'Stan magazynowy'],
  ['availability', 'Dostępność'], ['categories', 'Kategorie'], ['brand', 'Marka'],
  ['images', 'Zdjęcia'], ['attributes', 'Atrybuty'], ['variants', 'Warianty'],
  ['weight', 'Waga'], ['dimensions', 'Wymiary'],
] as const;

function routeFromHash(): ViewKey {
  const candidate = window.location.hash.replace(/^#\/?/, '') as ViewKey;
  return navigation.some((item) => item.key === candidate) ? candidate : 'dashboard';
}

export function App() {
  const [view, setView] = useState<ViewKey>(routeFromHash);
  const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  useEffect(() => {
    const change = () => setView(routeFromHash());
    window.addEventListener('hashchange', change);
    return () => window.removeEventListener('hashchange', change);
  }, []);

  useEffect(() => {
    if (!toast) return;
    const timeout = window.setTimeout(() => setToast(null), 4500);
    return () => window.clearTimeout(timeout);
  }, [toast]);

  const notify = useCallback((type: 'success' | 'error', message: string) => setToast({ type, message }), []);
  const current = navigation.find((item) => item.key === view) ?? navigation[0];

  return (
    <div className="psh-app">
      <aside className="psh-sidebar" aria-label="Nawigacja Supplier Hub">
        <div className="psh-brand">
          <span className="psh-brand__mark" aria-hidden="true">P</span>
          <span><strong>Pupilovo</strong><small>Supplier Hub</small></span>
        </div>
        <nav>
          {navigation.map((item) => (
            <a
              key={item.key}
              href={`#/${item.key}`}
              className={view === item.key ? 'is-active' : ''}
              aria-current={view === item.key ? 'page' : undefined}
            >
              <span className="psh-nav-icon" aria-hidden="true">{item.short}</span>
              <span>{item.label}</span>
            </a>
          ))}
        </nav>
        <div className="psh-sidebar__footer">Wersja {window.pupilovoSupplierHub.version}</div>
      </aside>
      <main className="psh-main" id="psh-main" tabIndex={-1}>
        <header className="psh-header">
          <div>
            <p className="psh-eyebrow">Pupilovo Supplier Hub</p>
            <h1>{current.label}</h1>
          </div>
          <span className="psh-stage">Supplier Hub · {window.pupilovoSupplierHub.version}</span>
        </header>
        {view === 'dashboard' && <Dashboard />}
        {view === 'suppliers' && <Suppliers notify={notify} />}
        {view === 'catalog' && <Catalog notify={notify} />}
        {view === 'fulfillment' && <FulfillmentView />}
        {view === 'categories' && <CategoriesView notify={notify} />}
        {view === 'selected' && <SelectedView notify={notify} />}
        {view === 'import' && <ImportView notify={notify} />}
        {view === 'sync' && <SyncView notify={notify} />}
        {view === 'pricing' && <PricingView notify={notify} />}
        {view === 'history' && <HistoryView notify={notify} />}
        {view === 'settings' && <SettingsView notify={notify} />}
      </main>
      {toast && (
        <div className={`psh-toast psh-toast--${toast.type}`} role="status" aria-live="polite">
          {toast.message}
          <button type="button" onClick={() => setToast(null)} aria-label="Zamknij komunikat">×</button>
        </div>
      )}
    </div>
  );
}

function Dashboard() {
  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api<DashboardMetrics>('/dashboard').then(setMetrics).catch((reason: Error) => setError(reason.message));
  }, []);

  const cards = metrics ? [
    ['Podłączone hurtownie', metrics.connectedSuppliers, 'suppliers'],
    ['Produkty w katalogach', metrics.catalogProducts, 'catalog'],
    ['Wybrane produkty', metrics.selectedProducts, 'selected'],
    ['Produkty w WooCommerce', metrics.importedProducts, 'import'],
    ['Wymagające uwagi', metrics.attentionErrors, 'history'],
    ['Produkty niedostępne', metrics.unavailableProducts, 'catalog'],
    ['Zmiany cen do akceptacji', metrics.priceChangesPending, 'pricing'],
  ] as const : [];

  return (
    <section aria-labelledby="dashboard-heading">
      <div className="psh-section-heading">
        <div><h2 id="dashboard-heading">Twój sklep w jednym miejscu</h2><p>Dodaj hurtownię, wybierz produkty i importuj je bez ręcznej obsługi WooCommerce.</p></div>
      </div>
      <div className="psh-panel psh-quick-start">
        <h3>Szybki start</h3>
        <p>1. Podłącz hurtownię → 2. Wybierz produkty z katalogu → 3. Kliknij „Importuj wybrane”.</p>
        <div className="psh-form-actions">
          <a className="psh-button" href="#/suppliers">1. Dodaj hurtownię</a>
          <a className="psh-button" href="#/catalog">2. Wybierz produkty</a>
          <a className="psh-button psh-button--primary" href="#/import">3. Importuj wybrane →</a>
        </div>
      </div>
      {error && <ErrorState message={error} />}
      {!metrics && !error && <SkeletonCards />}
      {metrics && (
        <>
          <div className="psh-metric-grid">
            {cards.map(([label, value, target]) => (
              <a href={`#/${target}`} className="psh-metric" key={label}>
                <span>{label}</span><strong>{value.toLocaleString('pl-PL')}</strong><small>Przejdź do widoku →</small>
              </a>
            ))}
          </div>
          <div className="psh-panel psh-sync-summary">
            <div><span className="psh-panel__label">Ostatnia synchronizacja</span><strong>{formatDate(metrics.lastSyncAt)}</strong></div>
            <p>Synchronizacja nie uruchomi się bez skonfigurowanego źródła i jawnego wyboru produktów.</p>
          </div>
        </>
      )}
    </section>
  );
}

function Suppliers({ notify }: { notify: (type: 'success' | 'error', message: string) => void }) {
  const [data, setData] = useState<PaginatedSuppliers | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Supplier | null>(null);

  const load = useCallback(async () => {
    setLoading(true); setError('');
    try { setData(await api<PaginatedSuppliers>('/suppliers?per_page=50')); }
    catch (reason) { setError((reason as Error).message); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => {
    let active = true;
    api<PaginatedSuppliers>('/suppliers?per_page=50')
      .then((response) => { if (active) setData(response); })
      .catch((reason: Error) => { if (active) setError(reason.message); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, []);

  const remove = async () => {
    if (!pendingDelete) return;
    try {
      await api(`/suppliers/${pendingDelete.id}?confirm=true`, { method: 'DELETE' });
      notify('success', 'Profil hurtowni usunięto. Produkty WooCommerce pozostały bez zmian.');
      setPendingDelete(null); await load();
    } catch (reason) { notify('error', (reason as Error).message); }
  };

  return (
    <section aria-labelledby="suppliers-heading">
      <div className="psh-section-heading">
        <div><h2 id="suppliers-heading">Profile hurtowni</h2><p>Każde źródło ma oddzielną konfigurację, mapowanie i stan synchronizacji.</p></div>
        <button className="psh-button psh-button--primary" type="button" onClick={() => setShowForm((value) => !value)}>
          {showForm ? 'Zamknij formularz' : '+ Dodaj hurtownię'}
        </button>
      </div>
      {showForm && <SupplierForm onCreated={async () => { setShowForm(false); notify('success', 'Profil hurtowni zapisany jako szkic.'); await load(); }} notify={notify} />}
      {error && <ErrorState message={error} />}
      {loading && <TableSkeleton />}
      {!loading && data?.items.length === 0 && <EmptyState />}
      {!loading && data && data.items.length > 0 && (
        <div className="psh-table-wrap">
          <table className="psh-table">
            <thead><tr><th>Nazwa</th><th>Źródło</th><th>Status</th><th>Ostatni import</th><th><span className="screen-reader-text">Akcje</span></th></tr></thead>
            <tbody>{data.items.map((supplier) => <SupplierRow key={supplier.id} supplier={supplier} notify={notify} onDelete={() => setPendingDelete(supplier)} />)}</tbody>
          </table>
        </div>
      )}
      {pendingDelete && (
        <ConfirmDialog
          title="Usunąć profil hurtowni?"
          message={`Profil „${pendingDelete.name}” zostanie wyłączony i ukryty. Powiązane produkty WooCommerce nie zostaną usunięte.`}
          onCancel={() => setPendingDelete(null)}
          onConfirm={() => void remove()}
        />
      )}
    </section>
  );
}

function SupplierRow({ supplier, notify, onDelete }: { supplier: Supplier; notify: (type: 'success' | 'error', message: string) => void; onDelete: () => void }) {
  const [expanded, setExpanded] = useState(false);

  return (
    <>
      <tr>
        <td><strong>{supplier.name}</strong><small className="psh-cell-note">{supplier.slug}</small></td>
        <td>{sourceLabels[supplier.sourceType] ?? supplier.sourceType}</td>
        <td><span className={`psh-badge psh-badge--${supplier.status}`}>{statusLabel(supplier.status)}</span></td>
        <td>{formatDate(supplier.lastImportAt)}</td>
        <td className="psh-actions">
          <button type="button" className="psh-link-button" onClick={() => setExpanded((value) => !value)}>{expanded ? 'Ukryj' : 'Konfiguruj'}</button>
          <button type="button" className="psh-link-button psh-link-button--danger" onClick={onDelete}>Usuń</button>
        </td>
      </tr>
      {expanded && (
        <tr className="psh-detail-row"><td colSpan={5}>
          <SourceWizard supplier={supplier} notify={notify} />
        </td></tr>
      )}
    </>
  );
}

function SourceWizard({ supplier, notify }: { supplier: Supplier; notify: (type: 'success' | 'error', message: string) => void }) {
  const [step, setStep] = useState(1);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [sourceType, setSourceType] = useState<string>(supplier.sourceType || 'file_xml');
  const [location, setLocation] = useState('');
  const [recordPath, setRecordPath] = useState(sourceType.includes('json') ? 'products' : 'product');
  const [delimiter, setDelimiter] = useState(';');
  const [allowHttp, setAllowHttp] = useState(false);
  const [complete, setComplete] = useState(false);
  const [authType, setAuthType] = useState('none');
  const [username, setUsername] = useState('');
  const [secret, setSecret] = useState('');
  const [inspection, setInspection] = useState<FeedInspection | null>(null);
  const [mapping, setMapping] = useState<Record<string, string>>(supplier.fieldMapping || {});
  const fileInput = useRef<HTMLInputElement>(null);

  useEffect(() => {
    let active = true;
    api<SupplierSource | null>(`/suppliers/${supplier.id}/source`).then((source) => {
      if (!active || !source) return;
      setSourceType(source.sourceType || supplier.sourceType || 'file_xml'); setLocation(source.location || '');
      setRecordPath(source.config.record_path || ''); setDelimiter(source.config.delimiter || ';');
      setAllowHttp(source.allowInsecureHttp); setComplete(source.declaresCompleteFeed);
      if (source.credentialsConfigured) setAuthType('configured');
    }).catch((reason: Error) => notify('error', reason.message)).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [supplier.id, supplier.sourceType, notify]);

  const saveConnection = async (event: FormEvent) => {
    event.preventDefault(); setSaving(true);
    try {
      await api(`/suppliers/${supplier.id}/source`, { method: 'PUT', body: JSON.stringify({ name: 'Źródło główne', sourceType, location: sourceType.startsWith('url_') ? location : null, allowInsecureHttp: allowHttp, declaresCompleteFeed: complete, config: { record_path: recordPath, delimiter } }) });
      if (authType !== 'none' && authType !== 'configured') {
        const credentials = authType === 'basic' ? { type: 'basic', username, password: secret } : { type: 'bearer', token: secret };
        await api(`/suppliers/${supplier.id}/source/credentials`, { method: 'PUT', body: JSON.stringify(credentials) });
      }
      setStep(2); notify('success', 'Konfiguracja źródła została zapisana.');
    } catch (reason) { notify('error', (reason as Error).message); }
    finally { setSaving(false); }
  };

  const inspect = async (event: FormEvent) => {
    event.preventDefault(); setSaving(true);
    const body = new FormData();
    if (sourceType.startsWith('file_')) { const file = fileInput.current?.files?.[0]; if (!file) { notify('error', 'Wybierz plik feedu.'); setSaving(false); return; } body.append('feed_file', file); }
    try { setInspection(await api<FeedInspection>(`/suppliers/${supplier.id}/source/inspect`, { method: 'POST', body })); setStep(3); notify('success', 'Struktura feedu została rozpoznana bez zapisu katalogu.'); }
    catch (reason) { notify('error', (reason as Error).message); }
    finally { setSaving(false); }
  };

  const saveMapping = async () => {
    setSaving(true);
    try { await api(`/suppliers/${supplier.id}`, { method: 'PUT', body: JSON.stringify({ fieldMapping: mapping }) }); notify('success', 'Mapowanie pól zostało zapisane.'); setStep(4); }
    catch (reason) { notify('error', (reason as Error).message); }
    finally { setSaving(false); }
  };

  if (loading) return <TableSkeleton />;
  return <div className="psh-wizard"><ol className="psh-steps"><li className={step >= 1 ? 'is-active' : ''}>1. Połączenie</li><li className={step >= 2 ? 'is-active' : ''}>2. Test</li><li className={step >= 3 ? 'is-active' : ''}>3. Mapowanie</li><li className={step >= 4 ? 'is-active' : ''}>4. Gotowe</li></ol>
    {step === 1 && <form className="psh-supplier-form" onSubmit={(event) => void saveConnection(event)}><div className="psh-form-grid">
      <label><span>Typ źródła</span><select value={sourceType} onChange={(event) => setSourceType(event.target.value)}>{['file_xml','url_xml','file_csv','url_csv','file_tsv','url_tsv','file_json','url_json'].map((type) => <option key={type} value={type}>{sourceLabels[type] || type}</option>)}</select></label>
      {sourceType.startsWith('url_') && <label><span>URL źródła</span><input type="url" value={location} onChange={(event) => setLocation(event.target.value)} required placeholder="https://hurtownia.example/feed" /></label>}
      {(sourceType.includes('xml') || sourceType.includes('json')) && <label><span>Ścieżka rekordów</span><input value={recordPath} onChange={(event) => setRecordPath(event.target.value)} required placeholder={sourceType.includes('xml') ? 'catalog.products.product' : 'data.products'} /></label>}
      {sourceType.includes('csv') && <label><span>Separator</span><input value={delimiter} onChange={(event) => setDelimiter(event.target.value)} maxLength={1} required /></label>}
      {sourceType.startsWith('url_') && <label><span>Uwierzytelnianie</span><select value={authType} onChange={(event) => setAuthType(event.target.value)}><option value="none">Brak</option><option value="basic">Basic Auth</option><option value="bearer">Bearer Token</option>{authType === 'configured' && <option value="configured">Już skonfigurowane</option>}</select></label>}
      {authType === 'basic' && <label><span>Login</span><input value={username} onChange={(event) => setUsername(event.target.value)} autoComplete="off" required /></label>}
      {(authType === 'basic' || authType === 'bearer') && <label><span>{authType === 'basic' ? 'Hasło' : 'Token'}</span><input type="password" value={secret} onChange={(event) => setSecret(event.target.value)} autoComplete="new-password" required /></label>}
    </div><label className="psh-check"><input type="checkbox" checked={complete} onChange={(event) => setComplete(event.target.checked)} /> Dostawca deklaruje pełny feed</label>{sourceType.startsWith('url_') && <label className="psh-check"><input type="checkbox" checked={allowHttp} onChange={(event) => setAllowHttp(event.target.checked)} /> Świadomie zezwalam na HTTP bez TLS</label>}<div className="psh-form-actions"><button className="psh-button psh-button--primary" disabled={saving}>Zapisz i przejdź dalej</button></div></form>}
    {step === 2 && <form className="psh-upload" onSubmit={(event) => void inspect(event)}><div><strong>Sprawdź połączenie i strukturę</strong><p>Odczytamy maksymalnie 5 rekordów bez zapisu katalogu.</p></div>{sourceType.startsWith('file_') && <input ref={fileInput} type="file" required />}<button className="psh-button psh-button--primary" disabled={saving}>{saving ? 'Sprawdzanie…' : 'Sprawdź źródło'}</button></form>}
    {step === 3 && inspection && <div><p><strong>Wykryto {inspection.fields.length} pól.</strong> Przypisz co najmniej ID i nazwę.</p><div className="psh-mapping-grid psh-supplier-form">{mappingFields.map(([key, label]) => <label key={key}><span>{label}</span><select value={mapping[key] || ''} onChange={(event) => setMapping({ ...mapping, [key]: event.target.value })}><option value="">Nie mapuj</option>{inspection.fields.map((field) => <option key={field} value={field}>{field}</option>)}</select></label>)}</div><div className="psh-form-actions"><button type="button" className="psh-button" onClick={() => setStep(2)}>Wstecz</button><button type="button" className="psh-button psh-button--primary" disabled={!mapping.external_id || !mapping.name || saving} onClick={() => void saveMapping()}>Zapisz mapowanie</button></div></div>}
    {step === 4 && <div className="psh-success-panel"><strong>Konfiguracja źródła jest kompletna.</strong><p>Katalog nie został jeszcze pobrany. Uruchomienie będzie realizowane jako odporne zadanie kolejki, a nie w żądaniu przeglądarki.</p><button type="button" className="psh-link-button" onClick={() => setStep(1)}>Edytuj konfigurację</button></div>}
  </div>;
}

function SupplierForm({ onCreated, notify }: { onCreated: () => Promise<void>; notify: (type: 'success' | 'error', message: string) => void }) {
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState('');
  const [sourceType, setSourceType] = useState('file_xml');
  const [recordElement, setRecordElement] = useState('product');
  const [delimiter, setDelimiter] = useState(';');
  const [mapping, setMapping] = useState<Record<string, string>>({});

  const submit = async (event: FormEvent) => {
    event.preventDefault(); setSaving(true);
    const input: SupplierInput = {
      name, sourceType, status: 'draft',
      sourceConfig: sourceType.endsWith('xml') ? { record_element: recordElement } : { delimiter },
      fieldMapping: Object.fromEntries(Object.entries(mapping).filter(([, value]) => value.trim() !== '')),
    };
    try { await api('/suppliers', { method: 'POST', body: JSON.stringify(input) }); await onCreated(); }
    catch (reason) { notify('error', (reason as Error).message); }
    finally { setSaving(false); }
  };

  return (
    <form className="psh-panel psh-supplier-form" onSubmit={(event) => void submit(event)}>
      <div className="psh-form-grid">
        <label><span>Nazwa hurtowni</span><input value={name} onChange={(event) => setName(event.target.value)} required maxLength={191} /></label>
        <label><span>Typ źródła</span><select value={sourceType} onChange={(event) => setSourceType(event.target.value)}>{Object.entries(sourceLabels).map(([value, label]) => <option key={value} value={value} disabled={value === 'api_adapter'}>{label}{value === 'api_adapter' ? ' — wymaga adaptera' : ''}</option>)}</select></label>
        {sourceType.endsWith('xml') && <label><span>Element produktu XML</span><input value={recordElement} onChange={(event) => setRecordElement(event.target.value)} pattern="[A-Za-z_][A-Za-z0-9_.-]*" /></label>}
        {sourceType.endsWith('csv') && <label><span>Separator CSV</span><input value={delimiter} onChange={(event) => setDelimiter(event.target.value)} maxLength={1} /></label>}
      </div>
      <details><summary>Mapowanie pól źródła</summary><p className="psh-help">Wpisz nazwę pola lub nagłówka z feedu. Puste wartości można uzupełnić później.</p>
        <div className="psh-mapping-grid">{mappingFields.map(([key, label]) => <label key={key}><span>{label}</span><input value={mapping[key] ?? ''} onChange={(event) => setMapping((current) => ({ ...current, [key]: event.target.value }))} placeholder="Nazwa pola w źródle" /></label>)}</div>
      </details>
      <div className="psh-form-actions"><button className="psh-button psh-button--primary" disabled={saving}>{saving ? 'Zapisywanie…' : 'Zapisz jako szkic'}</button></div>
    </form>
  );
}

function Catalog({ notify }: { notify: (type: 'success' | 'error', message: string) => void }) {
  const [data, setData] = useState<PaginatedCatalog | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({ search: '', availability: '', minPrice: '', maxPrice: '', sort: 'updated', direction: 'desc' });
  const [applied, setApplied] = useState(filters);

  useEffect(() => {
    let active = true;
    const params = new URLSearchParams({ page: String(page), per_page: '25', sort: applied.sort, direction: applied.direction });
    if (applied.search) params.set('search', applied.search);
    if (applied.availability) params.set('availability', applied.availability);
    if (applied.minPrice) params.set('min_price', applied.minPrice);
    if (applied.maxPrice) params.set('max_price', applied.maxPrice);
    api<PaginatedCatalog>(`/catalog?${params}`)
      .then((response) => { if (active) setData(response); })
      .catch((reason: Error) => { if (active) setError(reason.message); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [page, applied]);

  const applyFilters = (event: FormEvent) => {
    event.preventDefault();
    setLoading(true);
    setError('');
    setPage(1);
    setApplied({ ...filters });
  };

  return <section aria-labelledby="catalog-heading">
    <div className="psh-section-heading"><div><h2 id="catalog-heading">Katalog dostawców</h2><p>Wyniki są filtrowane i stronicowane po stronie serwera.</p></div>{data && <span className="psh-result-count">{data.total.toLocaleString('pl-PL')} produktów</span>}</div>
    <form className="psh-filterbar" onSubmit={applyFilters}>
      <label className="psh-grow"><span>Szukaj</span><input value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nazwa, SKU lub EAN" /></label>
      <label><span>Dostępność</span><select value={filters.availability} onChange={(event) => setFilters({ ...filters, availability: event.target.value })}><option value="">Wszystkie</option><option value="available">Dostępne</option><option value="unavailable">Niedostępne</option><option value="unknown">Nieznane</option></select></label>
      <label><span>Cena od</span><input type="number" min="0" step="0.01" value={filters.minPrice} onChange={(event) => setFilters({ ...filters, minPrice: event.target.value })} /></label>
      <label><span>Cena do</span><input type="number" min="0" step="0.01" value={filters.maxPrice} onChange={(event) => setFilters({ ...filters, maxPrice: event.target.value })} /></label>
      <label><span>Sortuj</span><select value={`${filters.sort}:${filters.direction}`} onChange={(event) => { const [sort, direction] = event.target.value.split(':'); setFilters({ ...filters, sort, direction }); }}><option value="updated:desc">Ostatnio zmienione</option><option value="name:asc">Nazwa A–Z</option><option value="price:asc">Cena rosnąco</option><option value="price:desc">Cena malejąco</option><option value="stock:desc">Stan malejąco</option></select></label>
      <button className="psh-button psh-button--primary">Filtruj</button>
    </form>
    {error && <ErrorState message={error} />}
    {loading && <TableSkeleton />}
    {!loading && data?.items.length === 0 && <div className="psh-panel psh-empty"><strong>Brak produktów dla wybranych filtrów</strong><p>Najpierw pobierz poprawnie skonfigurowany katalog hurtowni.</p></div>}
    {!loading && data && data.items.length > 0 && <>
      <div className="psh-table-wrap"><table className="psh-table psh-catalog-table"><thead><tr><th>Wybór</th><th>Produkt</th><th>Hurtownia</th><th>SKU / EAN</th><th>Cena zakupu</th><th>Stan</th><th>Kategoria</th><th>Status</th></tr></thead><tbody>{data.items.map((product) => <CatalogRow key={product.id} product={product} onSelection={async selected=>{try{await api('/selections',{method:'PUT',body:JSON.stringify({productIds:[product.id],selected})});setData(current=>current?{...current,items:current.items.map(item=>item.id===product.id?{...item,selected}:item)}:current);notify('success',selected?'Produkt wybrano.':'Produkt odznaczono.');}catch(e){notify('error',(e as Error).message);}}} />)}</tbody></table></div>
      <div className="psh-pagination"><button type="button" className="psh-button" disabled={page <= 1} onClick={() => { setLoading(true); setPage((value) => value - 1); }}>← Poprzednia</button><span>Strona {data.page} z {data.totalPages}</span><button type="button" className="psh-button" disabled={page >= data.totalPages} onClick={() => { setLoading(true); setPage((value) => value + 1); }}>Następna →</button></div>
    </>}
  </section>;
}

function CatalogRow({ product,onSelection }: { product: CatalogProduct;onSelection:(selected:boolean)=>Promise<void> }) {
  return <tr><td><input type="checkbox" checked={product.selected} onChange={e=>void onSelection(e.target.checked)} aria-label={`Wybierz ${product.name}`}/></td><td><div className="psh-product-cell">{product.primaryImageUrl ? <img src={product.primaryImageUrl} alt="" loading="lazy" /> : <span className="psh-product-placeholder" aria-hidden="true">P</span>}<div><strong>{product.name}</strong><small>{product.externalId}</small></div></div></td><td>{product.supplierName}</td><td>{product.sku || '—'}<small className="psh-cell-note">{product.ean || 'Brak EAN'}</small></td><td>{product.purchasePrice === null ? 'Brak ceny' : `${product.purchasePrice.toLocaleString('pl-PL', { minimumFractionDigits: 2 })} ${product.currency || ''}`}</td><td>{product.stockQuantity ?? '—'}<small className="psh-cell-note">{availabilityLabel(product.availability)}</small></td><td>{product.supplierCategoryPath || '—'}</td><td>{product.wcProductId ? <span className="psh-badge psh-badge--active">Powiązany</span> : product.selected ? <span className="psh-badge">Wybrany</span> : <span className="psh-badge">Katalog</span>}</td></tr>;
}

function ConfirmDialog({ title, message, onCancel, onConfirm }: { title: string; message: string; onCancel: () => void; onConfirm: () => void }) {
  const cancel = useRef<HTMLButtonElement>(null);
  useEffect(() => { cancel.current?.focus(); }, []);
  return <div className="psh-dialog-backdrop" role="presentation" onMouseDown={(event) => { if (event.currentTarget === event.target) onCancel(); }}><div className="psh-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-title"><h2 id="confirm-title">{title}</h2><p>{message}</p><div className="psh-form-actions"><button ref={cancel} type="button" className="psh-button" onClick={onCancel}>Anuluj</button><button type="button" className="psh-button psh-button--danger" onClick={onConfirm}>Usuń profil</button></div></div></div>;
}

function SkeletonCards() { return <div className="psh-metric-grid" aria-label="Ładowanie danych">{Array.from({ length: 7 }, (_, index) => <div className="psh-metric psh-skeleton" key={index}><span /><strong /><small /></div>)}</div>; }
function TableSkeleton() { return <div className="psh-table-skeleton" aria-label="Ładowanie listy"><span /><span /><span /></div>; }
function ErrorState({ message }: { message: string }) { return <div className="psh-error" role="alert"><strong>Nie udało się pobrać danych.</strong><span>{message}</span></div>; }
function EmptyState() { return <div className="psh-panel psh-empty"><strong>Nie ma jeszcze żadnej hurtowni</strong><p>Dodaj profil jako szkic. Samo zapisanie profilu nie uruchamia importu ani synchronizacji.</p></div>; }
function formatDate(value: string | null) { if (!value) return 'Jeszcze nie uruchamiano'; const normalized = value.includes('T') ? value : `${value.replace(' ', 'T')}Z`; return new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(normalized)); }
function statusLabel(status: Supplier['status']) { return { draft: 'Szkic', active: 'Aktywna', disabled: 'Wyłączona', error: 'Błąd' }[status]; }
function availabilityLabel(value: string | null) { return ({ available: 'Dostępny', unavailable: 'Niedostępny', unknown: 'Nieznany' } as Record<string, string>)[value || 'unknown'] || value || 'Nieznany'; }
