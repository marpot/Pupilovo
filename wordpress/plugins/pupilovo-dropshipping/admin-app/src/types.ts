export type ViewKey =
  | 'dashboard'
  | 'suppliers'
  | 'catalog'
  | 'categories'
  | 'selected'
  | 'import'
  | 'sync'
  | 'pricing'
  | 'history'
  | 'settings';

export interface DashboardMetrics {
  connectedSuppliers: number;
  catalogProducts: number;
  selectedProducts: number;
  importedProducts: number;
  unavailableProducts: number;
  priceChangesPending: number;
  attentionErrors: number;
  lastSyncAt: string | null;
}

export interface Supplier {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  sourceType: string;
  adapterKey: string | null;
  status: 'draft' | 'active' | 'disabled' | 'error';
  syncEnabled: boolean;
  sourceConfig: Record<string, string>;
  fieldMapping: Record<string, string>;
  scheduleConfig: Record<string, string>;
  lastImportAt: string | null;
  lastSyncAt: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface PaginatedSuppliers {
  items: Supplier[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
}

export interface PreviewResponse {
  items: Array<Record<string, string>>;
  limit: number;
  persisted: false;
}

export interface SupplierInput {
  name: string;
  sourceType: string;
  status: string;
  sourceConfig: Record<string, string>;
  fieldMapping: Record<string, string>;
}

export interface CatalogProduct {
  id: number;
  supplierId: number;
  supplierName: string;
  externalId: string;
  sku: string | null;
  ean: string | null;
  name: string;
  purchasePrice: number | null;
  currency: string | null;
  taxRate: number | null;
  stockQuantity: number | null;
  availability: string | null;
  supplierCategoryPath: string | null;
  primaryImageUrl: string | null;
  selected: boolean;
  wcProductId: number | null;
  version: number;
  updatedAt: string;
}

export interface PaginatedCatalog {
  items: CatalogProduct[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
}

export interface SupplierSource {
  id: number;
  supplierId: number;
  name: string;
  sourceType: string;
  location: string | null;
  config: Record<string, string>;
  allowInsecureHttp: boolean;
  declaresCompleteFeed: boolean;
  credentialsConfigured: boolean;
}

export interface FeedInspection {
  fields: string[];
  samples: Array<Record<string, unknown>>;
  format: string;
  recordPath: string;
  persisted: false;
}
