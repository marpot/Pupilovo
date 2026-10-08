export type ViewKey =
  | 'dashboard'
  | 'suppliers'
  | 'catalog'
  | 'fulfillment'
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

export type FulfillmentStatus =
  | 'pending'
  | 'ready'
  | 'manually_approved'
  | 'sent'
  | 'acknowledged'
  | 'shipped'
  | 'delivered'
  | 'failed'
  | 'cancelled';

export interface FulfillmentItemSnapshot {
  orderId: number;
  orderItemId: number;
  productId: number;
  variationId: number;
  productName: string;
  sku: string;
  quantity: number;
  subtotal: string;
  subtotalTax: string;
  total: string;
  totalTax: string;
  currency: string;
  supplierId: number | null;
  productLinkId: number | null;
  supplierExternalId: string | null;
  assignmentReason: string;
  capturedAt: string;
}

export interface FulfillmentItem {
  id: number;
  orderItemId: number;
  productId: number;
  variationId: number;
  supplierId: number | null;
  productLinkId: number | null;
  quantity: number;
  assignmentReason: string;
  snapshot: FulfillmentItemSnapshot;
  snapshotChecksum: string;
  createdAt: string;
}

export interface FulfillmentHistoryEntry {
  id: number;
  eventType: string;
  fromStatus: FulfillmentStatus | null;
  toStatus: FulfillmentStatus | null;
  errorCode: string | null;
  message: string;
  context: Record<string, unknown>;
  actorUserId: number;
  createdAt: string;
}

export interface FulfillmentGroup {
  id: number;
  uuid: string;
  orderId: number;
  supplierId: number | null;
  supplierName: string | null;
  status: FulfillmentStatus;
  requiresManualDecision: boolean;
  itemCount: number;
  totalQuantity: number;
  currency: string | null;
  errorCount: number;
  lastErrorCode: string | null;
  createdAt: string;
  updatedAt: string;
  items?: FulfillmentItem[];
  history?: FulfillmentHistoryEntry[];
}

export interface PaginatedFulfillmentGroups {
  items: FulfillmentGroup[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
}

export interface FulfillmentOrderDetails {
  orderId: number;
  groups: FulfillmentGroup[];
}
