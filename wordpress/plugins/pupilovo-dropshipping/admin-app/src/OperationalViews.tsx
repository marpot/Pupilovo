import { FormEvent, useCallback, useEffect, useState } from 'react';
import { api } from './api';
import type { PaginatedCatalog, PaginatedSuppliers, Supplier } from './types';

type Notify = (type: 'success' | 'error', message: string) => void;
type Category = { id:number; name:string; path:string; wcTermId:number|null; decision:string };
type WooCategory = { id:number; name:string; path:string };
type Job = { id:number; type:string; status:string; totalItems:number; processedItems:number; succeededItems:number; failedItems:number; createdAt:string; context:Record<string,unknown> };
type JobItem = { id:number; action:string; status:string; errorCode:string|null; after:{ product?:{name?:string}; pricing?:{salePriceGross?:number;currency?:string}; errors?:string[] } };
type PricingRule = { id:number; scopeType:string; scopeId:number|null; priority:number; active:boolean; config:Record<string,unknown> };

function useSuppliers() {
  const [suppliers,setSuppliers]=useState<Supplier[]>([]);
  useEffect(()=>{api<PaginatedSuppliers>('/suppliers?per_page=100').then(data=>setSuppliers(data.items)).catch(()=>setSuppliers([]));},[]);
  return suppliers;
}

export function CategoriesView({notify}:{notify:Notify}) {
  const suppliers=useSuppliers(); const [supplierId,setSupplierId]=useState(0); const [items,setItems]=useState<Category[]>([]); const [woo,setWoo]=useState<WooCategory[]>([]); const [loading,setLoading]=useState(false);
  const selectedSupplierId=supplierId||suppliers[0]?.id||0;
  const load=useCallback(async()=>{if(!selectedSupplierId)return;setLoading(true);try{const [categories,terms]=await Promise.all([api<{items:Category[]}>(`/categories?supplier_id=${selectedSupplierId}`),api<{items:WooCategory[]}>('/woocommerce/categories')]);setItems(categories.items);setWoo(terms.items);}catch(e){notify('error',(e as Error).message);}finally{setLoading(false);}},[selectedSupplierId,notify]);
  useEffect(()=>{void Promise.resolve().then(load);},[load]);
  const map=async(categoryId:number,termId:number)=>{try{await api('/category-mappings',{method:'POST',body:JSON.stringify({categoryId,decision:termId?'map':'skip',termId:termId||undefined})});notify('success','Decyzję kategorii zapisano.');await load();}catch(e){notify('error',(e as Error).message);}};
  return <section><Heading title="Kategorie i mapowania" text="Każda decyzja jest zapisywana oddzielnie dla hurtowni; nic nie zmienia istniejącej hierarchii automatycznie." />
    <SupplierPicker suppliers={suppliers} value={selectedSupplierId} onChange={setSupplierId}/>
    {loading?<Loading/>:<div className="psh-table-wrap"><table className="psh-table"><thead><tr><th>Kategoria dostawcy</th><th>Decyzja</th><th>Kategoria WooCommerce</th></tr></thead><tbody>{items.map(item=><tr key={item.id}><td><strong>{item.name}</strong><small className="psh-cell-note">{item.path}</small></td><td><span className="psh-badge">{item.decision}</span></td><td><select aria-label={`Mapowanie ${item.name}`} value={item.wcTermId??''} onChange={e=>void map(item.id,Number(e.target.value))}><option value="">Pomiń / nierozstrzygnięta</option>{woo.map(term=><option key={term.id} value={term.id}>{term.path}</option>)}</select></td></tr>)}</tbody></table>{items.length===0&&<Empty text="Brak kategorii. Najpierw pobierz katalog dostawcy."/>}</div>}
  </section>;
}

export function SelectedView({notify}:{notify:Notify}) {
  const [data,setData]=useState<PaginatedCatalog|null>(null);const [loading,setLoading]=useState(true);
  const load=useCallback(async()=>{setLoading(true);try{setData(await api<PaginatedCatalog>('/catalog?selected=yes&per_page=100'));}catch(e){notify('error',(e as Error).message);}finally{setLoading(false);}},[notify]);useEffect(()=>{void Promise.resolve().then(load);},[load]);
  const clear=async(id:number)=>{try{await api('/selections',{method:'PUT',body:JSON.stringify({productIds:[id],selected:false})});notify('success','Produkt usunięto z wyboru.');await load();}catch(e){notify('error',(e as Error).message);}};
  return <section><Heading title="Wybrane produkty" text="Tylko te pozycje mogą trafić do dry-run, importu i synchronizacji." />{loading?<Loading/>:<div className="psh-table-wrap"><table className="psh-table"><thead><tr><th>Produkt</th><th>Hurtownia</th><th>SKU / EAN</th><th>Status</th><th>Akcja</th></tr></thead><tbody>{data?.items.map(p=><tr key={p.id}><td><strong>{p.name}</strong></td><td>{p.supplierName}</td><td>{p.sku||'—'}<small className="psh-cell-note">{p.ean||'Brak EAN'}</small></td><td>{p.wcProductId?'Powiązany':'Do importu'}</td><td><button className="psh-link-button psh-link-button--danger" onClick={()=>void clear(p.id)}>Odznacz</button></td></tr>)}</tbody></table>{data?.items.length===0&&<Empty text="Nie wybrano jeszcze produktów."/>}</div>}</section>;
}

export function ImportView({notify}:{notify:Notify}) {
  const suppliers=useSuppliers();const [supplierId,setSupplierId]=useState(0);const [preview,setPreview]=useState<{job:Job;items:JobItem[]}|null>(null);const [busy,setBusy]=useState(false);const [confirm,setConfirm]=useState(false);
  const selectedSupplierId=supplierId||suppliers[0]?.id||0;
  const dryRun=async()=>{setBusy(true);setPreview(null);try{const result=await api<{job:Job;items:JobItem[]}>('/import-preview',{method:'POST',body:JSON.stringify({supplierId:selectedSupplierId})});setPreview(result);notify('success','Dry-run zakończony. WooCommerce nie został zmieniony.');}catch(e){notify('error',(e as Error).message);}finally{setBusy(false);}};
  const approve=async()=>{if(!preview||!confirm||preview.items.some(item=>item.action==='conflict'||item.status==='blocked'||item.errorCode||item.after.errors?.length))return;setBusy(true);try{await api(`/jobs/${preview.job.id}/approve`,{method:'POST',body:JSON.stringify({confirm:true})});notify('success','Import dodano do bezpiecznej kolejki.');setPreview(null);setConfirm(false);}catch(e){notify('error',(e as Error).message);}finally{setBusy(false);}};
  const quickImport=async()=>{
    if(!selectedSupplierId||busy)return;
    setBusy(true);setPreview(null);setConfirm(false);
    try{
      const plan=await api<{job:Job;items:JobItem[]}>('/import-preview',{method:'POST',body:JSON.stringify({supplierId:selectedSupplierId})});
      const totals=plan.job.context.summary as Record<string,number>|undefined;
      const ready=(totals?.create||0)+(totals?.update||0);
      if(!ready||!plan.items.length||plan.items.some(item=>item.errorCode||item.after.errors?.length)||((totals?.conflict||0)>0)){
        setPreview(plan);
        notify('error','Import wymaga sprawdzenia: brak gotowych produktów lub wykryto konflikty. Sprawdź plan poniżej.');
        return;
      }
      await api(`/jobs/${plan.job.id}/approve`,{method:'POST',body:JSON.stringify({confirm:true})});
      notify('success',`Dodano ${ready} produktów do kolejki importu. Produkty nowe będą szkicami.`);
    }catch(e){notify('error',(e as Error).message);}finally{setBusy(false);}
  };
  const summary=preview?.job.context.summary as Record<string,number>|undefined;
  return <section><Heading title="Import produktów do sklepu" text="Wybierz hurtownię i zaimportuj zaznaczone wcześniej produkty jednym kliknięciem. Nowe produkty zapisujemy jako szkice."/><div className="psh-panel psh-toolbar"><SupplierPicker suppliers={suppliers} value={selectedSupplierId} onChange={setSupplierId}/><button className="psh-button psh-button--primary" disabled={!selectedSupplierId||busy} onClick={()=>void quickImport()}>{busy?'Sprawdzanie i importowanie…':'Importuj wybrane jednym kliknięciem →'}</button><button className="psh-button" disabled={!selectedSupplierId||busy} onClick={()=>void dryRun()}>Sprawdź plan przed importem</button></div><p className="psh-help">System sam sprawdzi plan importu. W przypadku konfliktów zatrzyma operację i pokaże, co wymaga decyzji. Import obejmuje tylko produkty zaznaczone w katalogu.</p>
    {summary&&<div className="psh-metric-grid psh-metric-grid--compact">{Object.entries(summary).map(([key,value])=><div className="psh-metric" key={key}><span>{({create:'Nowe',update:'Aktualizacje',conflict:'Konflikty',skip:'Pominięte'} as Record<string,string>)[key]||key}</span><strong>{value}</strong></div>)}</div>}
    {preview&&<>{preview.items.some(item=>item.action==='conflict'||item.status==='blocked'||item.errorCode||item.after.errors?.length)&&<p role="alert" className="psh-help">Import zablokowany: rozwiąż konflikty produktów, przypisz kategorie lub popraw reguły cenowe, a następnie wygeneruj nowy plan.</p>}<div className="psh-table-wrap"><table className="psh-table"><thead><tr><th>Produkt</th><th>Operacja</th><th>Status</th><th>Cena brutto</th><th>Uwagi</th></tr></thead><tbody>{preview.items.map(item=><tr key={item.id}><td>{item.after.product?.name||`#${item.id}`}</td><td>{item.action}</td><td>{item.status}</td><td>{item.after.pricing?.salePriceGross??'—'} {item.after.pricing?.currency??''}</td><td>{item.after.errors?.join(', ')||item.errorCode||'—'}</td></tr>)}</tbody></table></div><label className="psh-check"><input type="checkbox" checked={confirm} onChange={e=>setConfirm(e.target.checked)}/> Potwierdzam plan i rozumiem, że utworzy szkice lub zaktualizuje wyłącznie pola zarządzane.</label><button className="psh-button psh-button--primary" disabled={!confirm||busy||!((summary?.create||0)+(summary?.update||0))||preview.items.some(item=>item.action==='conflict'||item.status==='blocked'||Boolean(item.errorCode)||Boolean(item.after.errors?.length))} onClick={()=>void approve()}>Zatwierdź i dodaj do kolejki</button></>}
  </section>;
}

export function SyncView({notify}:{notify:Notify}) {
  const suppliers=useSuppliers();const [supplierId,setSupplierId]=useState(0);const [fields,setFields]=useState(['price','stock']);const [interval,setInterval]=useState(86400);const [enabled,setEnabled]=useState(false);const [busy,setBusy]=useState(false);
  const selectedSupplierId=supplierId||suppliers[0]?.id||0;
  const toggle=(field:string)=>setFields(current=>current.includes(field)?current.filter(value=>value!==field):[...current,field]);
  const manual=async()=>{setBusy(true);try{await api('/sync',{method:'POST',body:JSON.stringify({supplierId:selectedSupplierId,managedFields:fields})});notify('success','Synchronizację dodano do kolejki.');}catch(e){notify('error',(e as Error).message);}finally{setBusy(false);}};
  const save=async()=>{setBusy(true);try{await api(`/suppliers/${selectedSupplierId}/schedule`,{method:'PUT',body:JSON.stringify({enabled,intervalSeconds:interval,managedFields:fields})});notify('success',enabled?'Harmonogram zapisano.':'Harmonogram wyłączono.');}catch(e){notify('error',(e as Error).message);}finally{setBusy(false);}};
  return <section><Heading title="Synchronizacja" text="Synchronizowane są wyłącznie produkty zaznaczone i trwale powiązane z wybraną hurtownią."/><div className="psh-panel psh-supplier-form"><SupplierPicker suppliers={suppliers} value={selectedSupplierId} onChange={value=>{setSupplierId(value);setEnabled(suppliers.find(s=>s.id===value)?.syncEnabled??false);}}/><fieldset><legend>Zarządzane pola</legend>{['price','stock','name','description','categories','attributes','images','weight','dimensions'].map(field=><label className="psh-check psh-check--inline" key={field}><input type="checkbox" checked={fields.includes(field)} onChange={()=>toggle(field)}/>{field}</label>)}</fieldset><div className="psh-form-actions"><button className="psh-button" disabled={busy||!selectedSupplierId||fields.length===0} onClick={()=>void manual()}>Synchronizuj teraz</button></div></div><div className="psh-panel psh-supplier-form"><h3>Harmonogram</h3><label className="psh-check"><input type="checkbox" checked={enabled} onChange={e=>setEnabled(e.target.checked)}/> Włącz synchronizację cykliczną</label><label><span>Interwał</span><select value={interval} onChange={e=>setInterval(Number(e.target.value))}><option value={3600}>Co godzinę</option><option value={21600}>Co 6 godzin</option><option value={43200}>Co 12 godzin</option><option value={86400}>Codziennie</option></select></label><div className="psh-form-actions"><button className="psh-button psh-button--primary" disabled={busy||!selectedSupplierId} onClick={()=>void save()}>Zapisz harmonogram</button></div></div></section>;
}

export function PricingView({notify}:{notify:Notify}) {
  const suppliers=useSuppliers();const [supplierId,setSupplierId]=useState(0);const [rules,setRules]=useState<PricingRule[]>([]);const [mode,setMode]=useState('markup');const [value,setValue]=useState('20');const [vatGross,setVatGross]=useState(false);const [busy,setBusy]=useState(false);
  const selectedSupplierId=supplierId||suppliers[0]?.id||0;
  const load=useCallback(async()=>{if(!selectedSupplierId)return;try{setRules((await api<{items:PricingRule[]}>(`/pricing-rules?supplier_id=${selectedSupplierId}`)).items);}catch(e){notify('error',(e as Error).message);}},[selectedSupplierId,notify]);useEffect(()=>{void Promise.resolve().then(load);},[load]);
  const save=async(e:FormEvent)=>{e.preventDefault();setBusy(true);try{await api('/pricing-rules',{method:'POST',body:JSON.stringify({supplierId:selectedSupplierId,scopeType:'supplier',priority:100,active:true,config:{mode,value:Number(value),purchasePriceIncludesTax:vatGross,rounding:{increment:.01,mode:'nearest'}}})});notify('success','Regułę cenową zapisano.');await load();}catch(err){notify('error',(err as Error).message);}finally{setBusy(false);}};
  const remove=async(id:number)=>{try{await api(`/pricing-rules/${id}?supplier_id=${selectedSupplierId}`,{method:'DELETE'});notify('success','Regułę usunięto.');await load();}catch(e){notify('error',(e as Error).message);}};
  return <section><Heading title="Ceny i marże" text="Marża i narzut są liczone odmiennie. Kalkulacja nie jest gwarancją rentowności."/><form className="psh-panel psh-supplier-form" onSubmit={e=>void save(e)}><SupplierPicker suppliers={suppliers} value={selectedSupplierId} onChange={setSupplierId}/><div className="psh-form-grid"><label><span>Model</span><select value={mode} onChange={e=>setMode(e.target.value)}><option value="markup">Narzut procentowy</option><option value="margin">Marża procentowa</option><option value="fixed">Stała kwota</option></select></label><label><span>Wartość</span><input type="number" min="0" step="0.01" value={value} onChange={e=>setValue(e.target.value)} required/></label></div><label className="psh-check"><input type="checkbox" checked={vatGross} onChange={e=>setVatGross(e.target.checked)}/> Cena zakupu dostawcy zawiera VAT</label><div className="psh-form-actions"><button className="psh-button psh-button--primary" disabled={busy||!selectedSupplierId}>Zapisz regułę</button></div></form><div className="psh-table-wrap"><table className="psh-table"><thead><tr><th>Zakres</th><th>Priorytet</th><th>Model</th><th>Wartość</th><th>Akcja</th></tr></thead><tbody>{rules.map(rule=><tr key={rule.id}><td>{rule.scopeType}{rule.scopeId?` #${rule.scopeId}`:''}</td><td>{rule.priority}</td><td>{String(rule.config.mode||'—')}</td><td>{String(rule.config.value??'—')}</td><td><button className="psh-link-button psh-link-button--danger" onClick={()=>void remove(rule.id)}>Usuń</button></td></tr>)}</tbody></table>{rules.length===0&&<Empty text="Brak reguł cenowych."/>}</div></section>;
}

export function HistoryView({notify}:{notify:Notify}) {
  const [jobs,setJobs]=useState<Job[]>([]);const [logs,setLogs]=useState<Array<{id:number;severity:string;code:string;message:string;createdAt:string}>>([]);const [loading,setLoading]=useState(true);
  const load=useCallback(async()=>{setLoading(true);try{const [jobData,logData]=await Promise.all([api<{items:Job[]}>('/jobs?per_page=50'),api<{items:typeof logs}>('/logs?per_page=50')]);setJobs(jobData.items);setLogs(logData.items);}catch(e){notify('error',(e as Error).message);}finally{setLoading(false);}},[notify]);useEffect(()=>{void Promise.resolve().then(load);},[load]);
  return <section><Heading title="Historia i błędy" text="Trwała historia zadań i zredagowane logi — bez sekretów źródeł."/><button className="psh-button" onClick={()=>void load()}>Odśwież</button>{loading?<Loading/>:<><h3>Zadania</h3><div className="psh-table-wrap"><table className="psh-table"><thead><tr><th>ID</th><th>Typ</th><th>Status</th><th>Postęp</th><th>Utworzono</th></tr></thead><tbody>{jobs.map(job=><tr key={job.id}><td>#{job.id}</td><td>{job.type}</td><td><span className="psh-badge">{job.status}</span></td><td>{job.processedItems}/{job.totalItems} · błędy {job.failedItems}</td><td>{job.createdAt}</td></tr>)}</tbody></table></div><h3>Logi</h3><div className="psh-table-wrap"><table className="psh-table"><thead><tr><th>Poziom</th><th>Kod</th><th>Komunikat</th><th>Data</th></tr></thead><tbody>{logs.map(log=><tr key={log.id}><td><span className={`psh-badge psh-badge--${log.severity}`}>{log.severity}</span></td><td>{log.code}</td><td>{log.message}</td><td>{log.createdAt}</td></tr>)}</tbody></table></div></>}</section>;
}

export function SettingsView({notify}:{notify:Notify}) {
  const [data,setData]=useState<Record<string,unknown>|null>(null);useEffect(()=>{api<Record<string,unknown>>('/diagnostics').then(setData).catch(e=>notify('error',(e as Error).message));},[notify]);
  return <section><Heading title="Ustawienia i diagnostyka" text="Stan rzeczywistego środowiska oraz wymaganych komponentów."/>{!data?<Loading/>:<div className="psh-panel psh-diagnostics">{Object.entries(data).map(([key,value])=><div key={key}><strong>{key}</strong><span>{typeof value==='object'?JSON.stringify(value):String(value??'—')}</span></div>)}</div>}<div className="psh-panel"><h3>Retencja danych</h3><p>Dezaktywacja nie usuwa danych. Usunięcie profilu nie usuwa produktów WooCommerce. Pełne usunięcie danych jest świadomą decyzją podczas odinstalowania.</p></div></section>;
}

function Heading({title,text}:{title:string;text:string}){return <div className="psh-section-heading"><div><h2>{title}</h2><p>{text}</p></div></div>}
function SupplierPicker({suppliers,value,onChange}:{suppliers:Supplier[];value:number;onChange:(value:number)=>void}){return <label className="psh-field"><span>Hurtownia</span><select value={value} onChange={e=>onChange(Number(e.target.value))}><option value={0}>Wybierz hurtownię</option>{suppliers.map(s=><option value={s.id} key={s.id}>{s.name}</option>)}</select></label>}
function Loading(){return <div className="psh-table-skeleton" aria-label="Ładowanie"><span/><span/><span/></div>}
function Empty({text}:{text:string}){return <div className="psh-empty-inline">{text}</div>}
